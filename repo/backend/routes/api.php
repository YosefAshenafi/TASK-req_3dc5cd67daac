<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlayHistoryController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AssetController as AdminAssetController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\DeviceReplayAuditController;
use App\Http\Controllers\Technician\ConsoleController;
use App\Http\Controllers\Device\EventIngestionController;
use App\Http\Middleware\DeviceApiKeyAuth;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/assets', [AssetController::class, 'index']);
    Route::post('/assets', [AssetController::class, 'store']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{favorite}', [FavoriteController::class, 'destroy']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::post('/playlists/redeem', [PlaylistController::class, 'redeem']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);
    Route::patch('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
    Route::post('/playlists/{playlist}/items', [PlaylistController::class, 'addItem']);
    Route::delete('/playlists/{playlist}/items/{item}', [PlaylistController::class, 'removeItem']);

    Route::get('/play-history', [PlayHistoryController::class, 'index']);
    Route::post('/play-history', [PlayHistoryController::class, 'store']);

    Route::get('/recommendations', [RecommendationController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::patch('/users/{user}/freeze', [AdminUserController::class, 'freeze']);
    Route::patch('/users/{user}/blacklist', [AdminUserController::class, 'blacklist']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/assets', [AdminAssetController::class, 'index']);
    Route::patch('/assets/{asset}/approve', [AdminAssetController::class, 'approve']);
    Route::patch('/assets/{asset}/reject', [AdminAssetController::class, 'reject']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/monitoring', [MonitoringController::class, 'index']);
    Route::post('/device-replays', [DeviceReplayAuditController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:technician'])->prefix('technician')->group(function (): void {
    Route::get('/devices', [ConsoleController::class, 'devices']);
    Route::get('/events', [ConsoleController::class, 'events']);
});

Route::middleware(DeviceApiKeyAuth::class)->prefix('device')->group(function (): void {
    Route::post('/events', [EventIngestionController::class, 'store']);
});
