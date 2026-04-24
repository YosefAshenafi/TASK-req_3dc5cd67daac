<?php

use App\Jobs\GenerateRecommendationCandidates;
use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\RecommendationCandidate;
use App\Models\User;

// Focused unit tests for the tag-cosine ranking logic inside
// GenerateRecommendationCandidates — we use a minimal DB, deterministic tag
// vectors, and assert order + score properties rather than just presence.

function seedFavoriteWithTags(User $user, array $tags): Asset
{
    $asset = Asset::factory()->create(['status' => 'ready']);
    Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);
    foreach ($tags as $tag) {
        AssetTag::create(['asset_id' => $asset->id, 'tag' => $tag]);
    }
    return $asset;
}

function seedCandidateWithTags(array $tags): Asset
{
    $asset = Asset::factory()->create(['status' => 'ready']);
    foreach ($tags as $tag) {
        AssetTag::create(['asset_id' => $asset->id, 'tag' => $tag]);
    }
    return $asset;
}

test('candidate with more overlapping tags ranks higher than one with fewer', function () {
    // User loves 'jazz' and 'blues'. The "heavy overlap" candidate shares both
    // tags; the "light overlap" one shares only 'jazz'. Cosine similarity must
    // rank the heavy candidate strictly higher.
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['jazz', 'blues']);

    $heavy = seedCandidateWithTags(['jazz', 'blues']);
    $light = seedCandidateWithTags(['jazz']);

    (new GenerateRecommendationCandidates($user->id))->handle();

    $heavyRow = RecommendationCandidate::where('user_id', $user->id)->where('asset_id', $heavy->id)->first();
    $lightRow = RecommendationCandidate::where('user_id', $user->id)->where('asset_id', $light->id)->first();

    expect($heavyRow)->not->toBeNull();
    expect($lightRow)->not->toBeNull();
    expect((float) $heavyRow->score)->toBeGreaterThan((float) $lightRow->score);
});

test('candidate with zero tag overlap does not get a recommendation row', function () {
    // A user who likes 'jazz' should never be recommended 'romance' content
    // just because it exists — zero dotProduct is skipped in the job.
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['jazz']);

    $miss = seedCandidateWithTags(['romance']);

    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(
        RecommendationCandidate::where('user_id', $user->id)
            ->where('asset_id', $miss->id)
            ->exists()
    )->toBeFalse();
});

test('candidates already favorited by the user are excluded from the result set', function () {
    // The job builds candidates from assets the user has NOT yet favorited or
    // played — never re-recommending what the user already has.
    $user = User::factory()->create();
    $known = seedFavoriteWithTags($user, ['jazz']);

    // Known asset shares tags with itself, but must not appear in results.
    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(
        RecommendationCandidate::where('user_id', $user->id)
            ->where('asset_id', $known->id)
            ->exists()
    )->toBeFalse();
});

test('candidates from play history are also excluded', function () {
    $user = User::factory()->create();
    $played = Asset::factory()->create(['status' => 'ready']);
    AssetTag::create(['asset_id' => $played->id, 'tag' => 'jazz']);
    PlayHistory::create(['user_id' => $user->id, 'asset_id' => $played->id, 'played_at' => now()]);

    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(
        RecommendationCandidate::where('user_id', $user->id)
            ->where('asset_id', $played->id)
            ->exists()
    )->toBeFalse();
});

test('untagged candidates are ignored regardless of user vector', function () {
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['jazz']);

    $naked = Asset::factory()->create(['status' => 'ready']); // no tags

    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(
        RecommendationCandidate::where('user_id', $user->id)
            ->where('asset_id', $naked->id)
            ->exists()
    )->toBeFalse();
});

test('non-ready assets are excluded even if they have matching tags', function () {
    // Degradation & privacy: a still-processing or failed asset must never
    // surface as a recommendation.
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['jazz']);

    $processing = Asset::factory()->create(['status' => 'processing']);
    $failed     = Asset::factory()->create(['status' => 'failed']);
    foreach ([$processing, $failed] as $a) {
        AssetTag::create(['asset_id' => $a->id, 'tag' => 'jazz']);
    }

    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(
        RecommendationCandidate::where('user_id', $user->id)
            ->whereIn('asset_id', [$processing->id, $failed->id])
            ->count()
    )->toBe(0);
});

test('reason_tags_json reflects only the intersection with the user vector', function () {
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['jazz', 'blues']);

    $candidate = seedCandidateWithTags(['jazz', 'rock']); // shares only jazz

    (new GenerateRecommendationCandidates($user->id))->handle();

    $row = RecommendationCandidate::where('user_id', $user->id)
        ->where('asset_id', $candidate->id)
        ->first();
    expect($row)->not->toBeNull();

    // reason_tags_json is stored as JSON; decode and assert intersection.
    $reasons = is_array($row->reason_tags_json)
        ? $row->reason_tags_json
        : (json_decode($row->reason_tags_json ?? '[]', true) ?? []);

    expect($reasons)->toContain('jazz');
    expect($reasons)->not->toContain('rock');
});

test('re-running the job upserts existing rows rather than creating duplicates', function () {
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['jazz']);
    $cand = seedCandidateWithTags(['jazz']);

    (new GenerateRecommendationCandidates($user->id))->handle();
    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(RecommendationCandidate::where('user_id', $user->id)->where('asset_id', $cand->id)->count())->toBe(1);
});

test('score is bounded in [0, 1] for any tag-vector configuration', function () {
    // Cosine similarity is always in [0, 1] for non-negative weight vectors.
    // A bug that divided by the wrong magnitude would exceed 1 and skew ranking.
    $user = User::factory()->create();
    seedFavoriteWithTags($user, ['a', 'b', 'c']);
    $cand = seedCandidateWithTags(['a', 'b', 'c']);

    (new GenerateRecommendationCandidates($user->id))->handle();

    $row = RecommendationCandidate::where('user_id', $user->id)->where('asset_id', $cand->id)->first();
    expect((float) $row->score)->toBeGreaterThan(0.0);
    expect((float) $row->score)->toBeLessThanOrEqual(1.0);
});

test('handle() with null userId iterates all non-deleted non-blacklisted users', function () {
    // Scheduler calls handle() with no user id — must regenerate for every
    // active user. Blacklisted and soft-deleted users must be skipped.
    $active      = User::factory()->create();
    $blacklisted = User::factory()->blacklisted()->create();
    $deleted     = User::factory()->create();
    $deleted->delete();

    foreach ([$active, $blacklisted, $deleted] as $u) {
        seedFavoriteWithTags($u, ['jazz']);
    }
    $cand = seedCandidateWithTags(['jazz']);

    (new GenerateRecommendationCandidates(null))->handle();

    expect(RecommendationCandidate::where('user_id', $active->id)->where('asset_id', $cand->id)->exists())->toBeTrue();
    expect(RecommendationCandidate::where('user_id', $blacklisted->id)->count())->toBe(0);
    expect(RecommendationCandidate::where('user_id', $deleted->id)->count())->toBe(0);
});
