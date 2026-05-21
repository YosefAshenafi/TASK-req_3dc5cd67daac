<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeDeletedRecords extends Command
{
    protected $signature = 'app:purge-deleted';
    protected $description = 'Hard-delete soft-deleted records older than 30 days';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        $counts = [
            'users' => User::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'playlists' => Playlist::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'favorites' => Favorite::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
            'play_history' => PlayHistory::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete(),
        ];

        Log::info('purge_deleted_records', $counts);

        return self::SUCCESS;
    }
}
