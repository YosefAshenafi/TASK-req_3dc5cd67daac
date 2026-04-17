<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PlayHistoryController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// --------------------------------------------------------------------------
// Public routes
// --------------------------------------------------------------------------

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/auth/login', [AuthController::class, 'login']);

// --------------------------------------------------------------------------
// Authenticated routes (Sanctum Bearer token)
// --------------------------------------------------------------------------

Route::middleware(['auth:sanctum'])->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Search
    Route::get('/search', [SearchController::class, 'index']);

    // Assets (read)
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{id}', [AssetController::class, 'show']);

    // Play recording
    Route::post('/assets/{id}/play', [PlayHistoryController::class, 'play']);

    // Favorites (PUT and DELETE, no standard resource index/store needed)
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::put('/favorites/{asset_id}', [FavoriteController::class, 'update']);
    Route::delete('/favorites/{asset_id}', [FavoriteController::class, 'destroy']);

    // Playlists - order matters: specific paths before parameterized ones
    Route::post('/playlists/redeem', [PlaylistController::class, 'redeem']);
    Route::delete('/playlists/shares/{id}', [PlaylistController::class, 'revokeShare']);
    Route::apiResource('/playlists', PlaylistController::class);
    Route::post('/playlists/{id}/share', [PlaylistController::class, 'share']);

    // History
    Route::get('/history', [PlayHistoryController::class, 'index']);
    Route::get('/now-playing', [PlayHistoryController::class, 'nowPlaying']);

    // Recommendations
    Route::get('/recommendations', [RecommendationController::class, 'index']);

    // -----------------------------------------------------------------------
    // Admin-only routes
    // -----------------------------------------------------------------------
    Route::middleware(['role:admin'])->group(function () {
        // Asset management
        Route::post('/assets', [AssetController::class, 'store']);
        Route::delete('/assets/{id}', [AssetController::class, 'destroy']);

        // User management
        Route::apiResource('/users', UserController::class);
        Route::patch('/users/{id}/freeze', [UserController::class, 'freeze']);
        Route::patch('/users/{id}/unfreeze', [UserController::class, 'unfreeze']);
        Route::patch('/users/{id}/blacklist', [UserController::class, 'blacklist']);

        // Monitoring
        Route::get('/monitoring/status', [MonitoringController::class, 'status']);
        Route::post('/monitoring/feature-flags/recommended/reset', [MonitoringController::class, 'resetRecommended']);
    });

    // -----------------------------------------------------------------------
    // Technician + Admin routes
    // -----------------------------------------------------------------------
    Route::middleware(['role:admin,technician'])->group(function () {
        Route::post('/devices/events', [DeviceController::class, 'ingestEvent']);
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::get('/devices/{id}/events', [DeviceController::class, 'events']);
        Route::post('/devices/{id}/replay', [DeviceController::class, 'replay']);
    });
});
