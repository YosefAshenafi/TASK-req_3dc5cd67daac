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
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                idempotency_key   TEXT NOT NULL UNIQUE,
                payload           TEXT NOT NULL,
                attempts          INTEGER NOT NULL DEFAULT 0,
                retry_count       INTEGER NOT NULL DEFAULT 0,
                next_retry_at     INTEGER NOT NULL DEFAULT 0,
                last_error        TEXT,
                last_attempted_at INTEGER,
                created_at        INTEGER NOT NULL
            )
        ');

        // Add new columns to existing databases that predate this schema
        foreach (['retry_count INTEGER NOT NULL DEFAULT 0', 'last_error TEXT', 'last_attempted_at INTEGER'] as $col) {
            try {
                $colName = explode(' ', $col)[0];
                $this->db->exec("ALTER TABLE buffered_events ADD COLUMN {$col}");
            } catch (\Throwable) {
                // Column already exists
            }
        }

        $this->db->exec('
            CREATE TABLE IF NOT EXISTS dead_letter (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                idempotency_key  TEXT NOT NULL,
                payload          TEXT NOT NULL,
                http_status      INTEGER,
                reason           TEXT,
                dead_lettered_at INTEGER NOT NULL,
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
                    // Buffer is full — move the incoming event to dead_letter rather than
                    // silently evicting the oldest buffered events (which would cause data
                    // loss of already-queued records during a prolonged backend outage).
                    Log::warning('GatewayRun: buffer at capacity — rejecting new inbox event', [
                        'buffered_count'  => $count,
                        'max_buffer'      => $this->maxBuffer,
                        'idempotency_key' => $data['idempotency_key'],
                    ]);
                    $dl = $this->db->prepare(
                        'INSERT INTO dead_letter (idempotency_key, payload, http_status, reason, dead_lettered_at, created_at) VALUES (:k, :p, :s, :r, :d, :c)'
                    );
                    $dl->execute([
                        ':k' => $data['idempotency_key'],
                        ':p' => $raw,
                        ':s' => 0,
                        ':r' => 'GATEWAY_BUFFER_FULL',
                        ':d' => time(),
                        ':c' => time(),
                    ]);
                    @unlink($file);
                    continue;
                }

                $stmt = $this->db->prepare('
                    INSERT OR IGNORE INTO buffered_events (idempotency_key, payload, attempts, retry_count, next_retry_at, created_at)
                    VALUES (:key, :payload, 0, 0, 0, :now)
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
            SELECT id, idempotency_key, payload, attempts, retry_count, created_at
            FROM buffered_events
            WHERE next_retry_at <= {$now}
            ORDER BY id ASC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $iKey     = $row['idempotency_key'];
            $payload  = json_decode($row['payload'], true);
            $deviceId = $payload['device_id'] ?? 'unknown';

            try {
                [$status, $body] = $this->post($iKey, $row['payload'], (int) $row['created_at']);

                $action = $this->classifyStatus($status);

                Log::info('GatewayRun flush', [
                    'device_id'           => $deviceId,
                    'idempotency_key'      => $iKey,
                    'status'              => $status,
                    'attempt'             => (int) $row['attempts'] + 1,
                    'action'              => $action,
                ]);

                if ($action === 'delete') {
                    $this->db->exec("DELETE FROM buffered_events WHERE id = {$row['id']}");
                } elseif ($action === 'dead_letter') {
                    $this->moveToDeadLetter($row, $status, 'Payload rejected by server (deterministic 400)');
                } else {
                    // retry — exponential backoff starting at 1s, capped at 300s (per requirements)
                    $attempts     = (int) $row['attempts'] + 1;
                    $retryCount   = (int) $row['retry_count'] + 1;
                    $backoff      = min(2 ** ($attempts - 1), 300);
                    $nextRetry    = $now + $backoff;
                    $stmt         = $this->db->prepare(
                        'UPDATE buffered_events SET attempts = :a, retry_count = :rc, next_retry_at = :r, last_error = :e, last_attempted_at = :lat WHERE id = :id'
                    );
                    $stmt->execute([
                        ':a'   => $attempts,
                        ':rc'  => $retryCount,
                        ':r'   => $nextRetry,
                        ':e'   => "HTTP {$status}",
                        ':lat' => $now,
                        ':id'  => $row['id'],
                    ]);

                    Log::info('GatewayRun retry scheduled', [
                        'device_id'          => $deviceId,
                        'idempotency_key'     => $iKey,
                        'status'             => $status,
                        'attempt'            => $attempts,
                        'next_backoff_seconds' => $backoff,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("GatewayRun flush: {$e->getMessage()}", ['id' => $row['id'], 'device_id' => $deviceId]);
                $attempts   = (int) $row['attempts'] + 1;
                $retryCount = (int) $row['retry_count'] + 1;
                $backoff    = min(2 ** ($attempts - 1), 300);
                $nextRetry  = $now + $backoff;
                $stmt       = $this->db->prepare(
                    'UPDATE buffered_events SET attempts = :a, retry_count = :rc, next_retry_at = :r, last_error = :e, last_attempted_at = :lat WHERE id = :id'
                );
                $stmt->execute([
                    ':a'   => $attempts,
                    ':rc'  => $retryCount,
                    ':r'   => $nextRetry,
                    ':e'   => $e->getMessage(),
                    ':lat' => $now,
                    ':id'  => $row['id'],
                ]);
            }
        }
    }

    /**
     * Classify an HTTP status code into 'delete', 'dead_letter', or 'retry'.
     */
    private function classifyStatus(int $status): string
    {
        // Terminal success outcomes
        if (in_array($status, [200, 201, 202, 410], true)) {
            return 'delete';
        }

        // Payload-deterministic rejection — move to dead letter, do not retry
        if ($status === 400) {
            return 'dead_letter';
        }

        // Auth errors, conflicts, rate limits, server errors, network failure — always retry
        // 401, 403, 408, 409, 425, 429, 5xx, 0 (no response)
        return 'retry';
    }

    private function moveToDeadLetter(array $row, int $status, string $reason): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO dead_letter (idempotency_key, payload, http_status, reason, dead_lettered_at, created_at) VALUES (:k, :p, :s, :r, :d, :c)'
        );
        $stmt->execute([
            ':k' => $row['idempotency_key'],
            ':p' => $row['payload'],
            ':s' => $status,
            ':r' => $reason,
            ':d' => time(),
            ':c' => $row['created_at'],
        ]);
        $this->db->exec("DELETE FROM buffered_events WHERE id = {$row['id']}");
    }

    /**
     * POST an event to the gateway endpoint.
     * Returns [http_status, response_body].
     */
    private function post(string $idempotencyKey, string $payloadJson, int $bufferedAt): array
    {
        $gatewayToken = config('smartpark.gateway.token') ?: env('GATEWAY_TOKEN', '');

        $ch = curl_init("{$this->apiUrl}/gateway/events");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "X-Idempotency-Key: {$idempotencyKey}",
                'X-Buffered: true',
                "X-Buffered-At: " . date('c', $bufferedAt),
                "X-Gateway-Token: {$gatewayToken}",
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status ?: 0, $body ?: ''];
    }

    private function maybeHealthCheck(): void
    {
        $now = time();
        if ($now - $this->lastHealthAt < 30) {
            return;
        }
        $this->lastHealthAt = $now;
        $count     = (int) $this->db->query('SELECT COUNT(*) FROM buffered_events')->fetchColumn();
        $deadCount = (int) $this->db->query('SELECT COUNT(*) FROM dead_letter')->fetchColumn();
        $this->line(json_encode(['status' => 'ok', 'buffered_count' => $count, 'dead_letter_count' => $deadCount]));
    }
}
