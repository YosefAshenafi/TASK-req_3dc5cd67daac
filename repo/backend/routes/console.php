<?php

use App\Jobs\GenerateRecommendationCandidates;
use App\Jobs\PurgeSoftDeletedUsers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge soft-deleted users daily
Schedule::job(new PurgeSoftDeletedUsers)->daily();

// Generate recommendation candidates hourly
Schedule::job(new GenerateRecommendationCandidates)->hourly();

// Monitoring sampler (circuit breaker check) every minute
// Note: Laravel scheduler runs at 1-minute granularity; for 30-second intervals,
// we schedule twice per minute using appendOutputTo or repeat trick
Schedule::command('app:monitoring-sample')->everyThirtySeconds();
