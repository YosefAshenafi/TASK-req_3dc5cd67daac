<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check {--quiet}';
    protected $description = 'Check application health';

    public function handle(): int
    {
        try {
            DB::select('SELECT 1');
            if (!$this->option('quiet')) {
                $this->info('OK');
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (!$this->option('quiet')) {
                $this->error('FAIL: ' . $e->getMessage());
            }
            return self::FAILURE;
        }
    }
}
