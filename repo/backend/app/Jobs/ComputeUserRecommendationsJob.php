<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Asset;
use App\Models\PlayHistory;
use App\Models\RecommendationScore;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ComputeUserRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;
    public bool $unique = true;

    public function __construct(private readonly int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $playedTagCounts = DB::table('play_history')
            ->join('assets', 'play_history.asset_id', '=', 'assets.id')
            ->where('play_history.user_id', $this->userId)
            ->whereNull('play_history.deleted_at')
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(assets.tags, CONCAT("$[", seq.n, "]"))) as tag, COUNT(*) as cnt')
            ->crossJoin(DB::raw('(SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) seq'))
            ->whereRaw('seq.n < JSON_LENGTH(assets.tags)')
            ->groupBy('tag')
            ->pluck('cnt', 'tag')
            ->toArray();

        $candidates = Asset::where('status', 'approved')
            ->orderByDesc('play_count')
            ->limit(100)
            ->get();

        $scores = [];

        foreach ($candidates as $asset) {
            $tagAffinity = 0.0;
            foreach ($asset->tags ?? [] as $tag) {
                $tagAffinity += ($playedTagCounts[$tag] ?? 0) * 2;
            }

            $score = $tagAffinity + ($asset->play_count * 0.1);
            $scores[] = [
                'user_id' => $this->userId,
                'asset_id' => $asset->id,
                'score' => round($score, 4),
                'computed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        $scores = array_slice($scores, 0, 100);

        foreach (array_chunk($scores, 50) as $chunk) {
            DB::table('recommendation_scores')->upsert(
                $chunk,
                ['user_id', 'asset_id'],
                ['score', 'computed_at', 'updated_at']
            );
        }
    }
}
