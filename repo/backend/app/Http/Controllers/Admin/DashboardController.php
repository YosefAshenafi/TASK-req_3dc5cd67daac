<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Device;
use App\Models\PlayHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('account_status', 'active')->count(),
                'frozen' => User::where('account_status', 'frozen')->count(),
                'blacklisted' => User::where('account_status', 'blacklisted')->count(),
            ],
            'assets' => [
                'total' => Asset::count(),
                'pending' => Asset::where('status', 'pending')->count(),
                'approved' => Asset::where('status', 'approved')->count(),
                'rejected' => Asset::where('status', 'rejected')->count(),
            ],
            'plays' => [
                'total' => PlayHistory::count(),
                'last_24h' => PlayHistory::where('played_at', '>=', now()->subDay())->count(),
                'last_7d' => PlayHistory::where('played_at', '>=', now()->subDays(7))->count(),
            ],
            'devices' => [
                'total' => Device::count(),
                'active_last_24h' => Device::where('last_event_at', '>=', now()->subDay())->count(),
            ],
        ];

        return response()->json(['data' => ['stats' => $stats]]);
    }
}
