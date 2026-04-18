<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const DEFAULTS = [
        'site_name'      => 'SmartPark',
        'site_tagline'   => 'Find and discover media assets',
        'available_tags' => '["Safety","Overnight","Gate Issues","Parking","Event","General","Emergency"]',
    ];

    public function show(): JsonResponse
    {
        return response()->json($this->resolve());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_name'        => 'sometimes|string|max:80',
            'site_tagline'     => 'sometimes|string|max:200',
            'available_tags'   => 'sometimes|array|max:50',
            'available_tags.*' => 'string|max:60',
        ]);

        foreach ($validated as $key => $value) {
            AppSetting::setValue($key, $value);
        }

        return response()->json($this->resolve());
    }

    private function resolve(): array
    {
        $tags = AppSetting::getValue('available_tags', self::DEFAULTS['available_tags']);

        return [
            'site_name'      => AppSetting::getValue('site_name', self::DEFAULTS['site_name']),
            'site_tagline'   => AppSetting::getValue('site_tagline', self::DEFAULTS['site_tagline']),
            'available_tags' => is_array($tags) ? $tags : (json_decode($tags, true) ?? []),
        ];
    }
}
