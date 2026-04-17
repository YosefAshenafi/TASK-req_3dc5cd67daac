<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeSoftDeletedUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Permanently delete users soft-deleted more than 30 days ago.
     */
    public function handle(): void
    {
        $cutoff = now()->subDays(30);

        $count = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->forceDelete();

        Log::info("PurgeSoftDeletedUsers: Permanently deleted {$count} users soft-deleted before {$cutoff}.");
    }
}
