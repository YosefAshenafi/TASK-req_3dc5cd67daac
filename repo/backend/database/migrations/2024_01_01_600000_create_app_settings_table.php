<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            ['key' => 'site_name',      'value' => 'SmartPark',                           'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_tagline',   'value' => 'Find and discover media assets',       'created_at' => now(), 'updated_at' => now()],
            ['key' => 'available_tags', 'value' => '["Safety","Overnight","Gate Issues","Parking","Event","General","Emergency"]', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
