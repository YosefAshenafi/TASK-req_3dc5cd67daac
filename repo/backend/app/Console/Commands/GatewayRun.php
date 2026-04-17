<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GatewayRun extends Command
{
    protected $signature = 'gateway:run';
    protected $description = 'Run the offline device event gateway (SQLite buffer + flush loop).';

    private \PDO $db;
    private string $inboxPath;
    private string $apiUrl;
    private int $maxBuffer;
    private int $lastHealthAt = 0;

    public function handle(): int
    {
        $bufferPath      = env('GATEWAY_BUFFER_PATH', '/var/spool/smartpark/buffer.sqlite');
        $this->inboxPath = env('GATEWAY_INBOX_PATH', '/var/spool/smartpark/inbox');
        $this->apiUrl    = rtrim(env('GATEWAY_API_URL', 'http://nginx/api'), '/');
        $this->maxBuffer = (int) env('GATEWAY_MAX_BUFFER', 10000);

        $this->initDatabase($bufferPath);

        if (! is_dir($this->inboxPath)) {
            @mkdir($this->inboxPath, 0755, true);
        }

        $running = true;
        pcntl_signal(SIGTERM, function () use (&$running) {
            $running = false;
        });

        $this->line('gateway:run started.');

        while ($running) {
            pcntl_signal_dispatch();
            $this->scanInbox();
            $this->flush();
            $this->maybeHealthCheck();
            sleep(1);
        }

        return Command::SUCCESS;
    }

    private function initDatabase(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->db = new \PDO("sqlite:{$path}");
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA journal_mode=WAL');
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS buffered_events (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                idempotency_key  TEXT NOT NULL UNIQUE,
                payload          TEXT NOT NULL,
                attempts         INTEGER NOT NULL DEFAULT 0,
                next_retry_at    INTEGER NOT NULL DEFAULT 0,
                created_at       INTEGER NOT NULL
            )
        ');
    }

    private function scanInbox(): void
    {
        $files = glob($this->inboxPath . '/*.json') ?: [];

        foreach ($files as $file) {
            try {
                $raw  = file_get_contents($file);
                $data = json_decode($raw, true);

                if (! is_array($data) || empty($data['idempotency_key'])) {
                    @unlink($file);
                    continue;
                }

                $count = (int) $this->db->query('SELECT COUNT(*) FROM buffered_events')->fetchColumn();
                if ($count >= $this->maxBuffer) {
                    $excess = $count - $this->maxBuffer + 1;
                    $this->db->exec("DELETE FROM buffered_events WHERE id IN (SELECT id FROM buffered_events ORDER BY id ASC LIMIT {$excess})");
                }

                $stmt = $this->db->prepare('
                    INSERT OR IGNORE INTO buffered_events (idempotency_key, payload, attempts, next_retry_at, created_at)
                    VALUES (:key, :payload, 0, 0, :now)
                ');
                $stmt->execute([
                    ':key'     => $data['idempotency_key'],
                    ':payload' => $raw,
                    ':now'     => time(),
                ]);

                @unlink($file);
            } catch (\Throwable $e) {
                Log::error("GatewayRun scanInbox: {$e->getMessage()}", ['file' => $file]);
            }
        }
    }

    private function flush(): void
    {
        $now  = time();
        $rows = $this->db->query("
            SELECT id, idempotency_key, payload, attempts
            FROM buffered_events
            WHERE next_retry_at <= {$now}
            ORDER BY id ASC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            try {
                $status = $this->post($row['idempotency_key'], $row['payload']);

                if (in_array($status, [200, 201, 410], true) || ($status >= 400 && $status < 500 && $status !== 429)) {
                    $this->db->exec("DELETE FROM buffered_events WHERE id = {$row['id']}");
                } else {
                    $attempts     = (int) $row['attempts'] + 1;
                    $nextRetry    = $now + min(2 ** $attempts, 300);
                    $stmt         = $this->db->prepare('UPDATE buffered_events SET attempts = :a, next_retry_at = :r WHERE id = :id');
                    $stmt->execute([':a' => $attempts, ':r' => $nextRetry, ':id' => $row['id']]);
                }
            } catch (\Throwable $e) {
                Log::error("GatewayRun flush: {$e->getMessage()}", ['id' => $row['id']]);
                $attempts  = (int) $row['attempts'] + 1;
                $nextRetry = $now + min(2 ** $attempts, 300);
                $stmt      = $this->db->prepare('UPDATE buffered_events SET attempts = :a, next_retry_at = :r WHERE id = :id');
                $stmt->execute([':a' => $attempts, ':r' => $nextRetry, ':id' => $row['id']]);
            }
        }
    }

    private function post(string $idempotencyKey, string $payloadJson): int
    {
        $ch = curl_init("{$this->apiUrl}/devices/events");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "X-Idempotency-Key: {$idempotencyKey}",
                'X-Buffered: true',
            ],
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status ?: 0;
    }

    private function maybeHealthCheck(): void
    {
        $now = time();
        if ($now - $this->lastHealthAt < 30) {
            return;
        }
        $this->lastHealthAt = $now;
        $count = (int) $this->db->query('SELECT COUNT(*) FROM buffered_events')->fetchColumn();
        $this->line(json_encode(['status' => 'ok', 'buffered_count' => $count]));
    }
}
