<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Device;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\RecommendationScore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        RecommendationScore::truncate();
        PlayHistory::truncate();
        Favorite::truncate();
        PlaylistItem::truncate();
        Playlist::truncate();
        Asset::truncate();
        Device::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@smartpark.local',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Regular User',
            'username' => 'user',
            'email' => 'user@smartpark.local',
            'password' => Hash::make('Password123!'),
            'role' => 'user',
            'account_status' => 'active',
        ]);

        User::create([
            'name' => 'Tech User',
            'username' => 'tech',
            'email' => 'tech@smartpark.local',
            'password' => Hash::make('Password123!'),
            'role' => 'technician',
            'account_status' => 'active',
        ]);

        Device::create([
            'name' => 'Gate-001',
            'device_type' => 'gate',
            'api_key_hash' => hash('sha256', 'smartpark-device-key-abc123'),
            'last_sequence' => 0,
        ]);

        Device::create([
            'name' => 'Camera-001',
            'device_type' => 'camera',
            'api_key_hash' => hash('sha256', 'smartpark-device-key-camera01'),
            'last_sequence' => 0,
        ]);

        $asset1 = Asset::create([
            'title' => 'Parking Lot A Announcement',
            'description' => 'Welcome message for Lot A visitors',
            'tags' => ['announcement', 'welcome', 'lot-a'],
            'mime_type' => 'audio/mpeg',
            'file_path' => 'media/sample-audio-1.mp3',
            'file_size' => 1024 * 512,
            'duration' => 30,
            'status' => 'approved',
            'uploaded_by' => $admin->id,
            'play_count' => 45,
        ]);

        $asset2 = Asset::create([
            'title' => 'Safety Briefing Video',
            'description' => 'Required safety information for new staff',
            'tags' => ['safety', 'training', 'required'],
            'mime_type' => 'video/mp4',
            'file_path' => 'media/sample-video-1.mp4',
            'file_size' => 1024 * 1024 * 50,
            'duration' => 180,
            'status' => 'approved',
            'uploaded_by' => $admin->id,
            'play_count' => 120,
        ]);

        $asset3 = Asset::create([
            'title' => 'Monthly Newsletter',
            'description' => 'May 2026 newsletter for all staff',
            'tags' => ['newsletter', 'may-2026', 'staff'],
            'mime_type' => 'application/pdf',
            'file_path' => 'media/sample-doc-1.pdf',
            'file_size' => 1024 * 200,
            'duration' => null,
            'status' => 'approved',
            'uploaded_by' => $admin->id,
            'play_count' => 8,
        ]);

        $asset4 = Asset::create([
            'title' => 'Holiday Greeting',
            'description' => 'Season greetings audio clip',
            'tags' => ['holiday', 'greeting', 'audio'],
            'mime_type' => 'audio/mpeg',
            'file_path' => 'media/sample-audio-2.mp3',
            'file_size' => 1024 * 256,
            'duration' => 15,
            'status' => 'pending',
            'uploaded_by' => $user->id,
            'play_count' => 0,
        ]);

        foreach ([$asset1, $asset2, $asset3] as $asset) {
            DB::table('asset_search_index')->insertOrIgnore([
                'asset_id' => $asset->id,
                'search_text' => implode(' ', array_filter([
                    $asset->title,
                    $asset->description,
                    implode(' ', $asset->tags ?? []),
                ])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => 'My Favorites Playlist',
            'description' => 'Personal collection',
            'share_code' => 'DEMO1234',
        ]);

        PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset1->id, 'position' => 0]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset2->id, 'position' => 1]);

        Favorite::create(['user_id' => $user->id, 'asset_id' => $asset1->id]);
        Favorite::create(['user_id' => $user->id, 'asset_id' => $asset2->id]);

        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset1->id, 'played_at' => now()->subHours(2)]);
        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset2->id, 'played_at' => now()->subHour()]);
        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset1->id, 'played_at' => now()->subMinutes(30)]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $asset1->id,
            'score' => 9.5,
            'computed_at' => now(),
        ]);
        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $asset2->id,
            'score' => 7.2,
            'computed_at' => now(),
        ]);
    }
}
