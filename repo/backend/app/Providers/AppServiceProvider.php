<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\PurgeDeletedRecords;
use App\Console\Commands\UnfreezeExpiredAccounts;
use App\Console\Commands\ProcessFileDropEvents;
use App\Jobs\FlushBufferedDeviceEventsJob;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\DegradationService::class);
        $this->app->singleton(\App\Services\ScanHookService::class);
    }

    public function boot(): void
    {
        Schedule::command(PurgeDeletedRecords::class)->daily();
        Schedule::command(UnfreezeExpiredAccounts::class)->everyFiveMinutes();
        Schedule::command(ProcessFileDropEvents::class)->everyMinute();
        Schedule::job(FlushBufferedDeviceEventsJob::class)->everyMinute();
    }
}
