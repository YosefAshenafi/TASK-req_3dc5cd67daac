<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringSample extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:monitoring-sample';

    /**
     * The console command description.
     */
    protected $description = 'Sample system metrics and check circuit breaker state every 30 seconds.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Check circuit breaker: if failure count exceeds threshold, disable recommended flag
            $failureCount = (int) Cache::get('circuit_breaker:recommendation_failures', 0);
            $threshold    = (int) config('smartpark.circuit_breaker_threshold', 10);

            if ($failureCount >= $threshold) {
                $flag = FeatureFlag::find('recommended_enabled');
                if ($flag && $flag->enabled) {
                    $flag->enabled    = false;
                    $flag->reason     = "Circuit breaker tripped: {$failureCount} failures detected.";
                    $flag->updated_at = now();
                    $flag->save();

                    Log::warning("MonitoringSample: Circuit breaker tripped. Disabled recommended_enabled flag.");
                }
            }

            // Sample and store basic health metrics in cache for monitoring endpoint
            Cache::put('monitoring:last_sample_at', now()->toIso8601String(), 120);

            $this->info('Monitoring sample completed.');
        } catch (\Throwable $e) {
            Log::error("MonitoringSample: Error during sampling: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
