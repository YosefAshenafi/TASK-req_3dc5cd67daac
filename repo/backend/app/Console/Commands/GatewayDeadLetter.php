<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GatewayDeadLetter extends Command
{
    protected $signature = 'gateway:dead-letter
                            {--list   : List all dead-lettered events}
                            {--requeue= : Requeue a dead-lettered event by its idempotency_key}';

    protected $description = 'Inspect and requeue dead-lettered gateway events.';

    private \PDO $db;

    public function handle(): int
    {
        $bufferPath = env('GATEWAY_BUFFER_PATH', '/var/spool/smartpark/buffer.sqlite');

        if (! file_exists($bufferPath)) {
            $this->error("Buffer database not found at {$bufferPath}.");
            return Command::FAILURE;
        }

        $this->db = new \PDO("sqlite:{$bufferPath}");
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if ($this->option('list')) {
            return $this->listDeadLetters();
        }

        if ($key = $this->option('requeue')) {
            return $this->requeue($key);
        }

        $this->info('Use --list to list dead-lettered events or --requeue=<idempotency_key> to requeue one.');
        return Command::SUCCESS;
    }

    private function listDeadLetters(): int
    {
        $rows = $this->db->query(
            'SELECT id, idempotency_key, http_status, reason, dead_lettered_at FROM dead_letter ORDER BY dead_lettered_at DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $this->info('No dead-lettered events.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Idempotency Key', 'HTTP Status', 'Reason', 'Dead-lettered At'],
            array_map(fn ($r) => [
                $r['id'],
                $r['idempotency_key'],
                $r['http_status'],
                $r['reason'],
                date('Y-m-d H:i:s', (int) $r['dead_lettered_at']),
            ], $rows)
        );

        return Command::SUCCESS;
    }

    private function requeue(string $key): int
    {
        $row = $this->db->prepare('SELECT * FROM dead_letter WHERE idempotency_key = :k');
        $row->execute([':k' => $key]);
        $event = $row->fetch(\PDO::FETCH_ASSOC);

        if (! $event) {
            $this->error("Dead-lettered event with key {$key} not found.");
            return Command::FAILURE;
        }

        $stmt = $this->db->prepare(
            'INSERT OR IGNORE INTO buffered_events (idempotency_key, payload, attempts, retry_count, next_retry_at, created_at) VALUES (:k, :p, 0, 0, 0, :now)'
        );
        $stmt->execute([
            ':k'   => $event['idempotency_key'],
            ':p'   => $event['payload'],
            ':now' => time(),
        ]);

        $this->db->prepare('DELETE FROM dead_letter WHERE idempotency_key = :k')->execute([':k' => $key]);

        $this->info("Requeued event {$key}.");
        return Command::SUCCESS;
    }
}
