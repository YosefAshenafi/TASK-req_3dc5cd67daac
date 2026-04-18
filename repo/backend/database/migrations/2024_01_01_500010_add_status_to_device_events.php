<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            $table->string('status', 20)->default('accepted')->after('is_out_of_order');
            $table->boolean('buffered_by_gateway')->default(false)->after('status');
            $table->timestamp('buffered_at')->nullable()->after('buffered_by_gateway');
        });
    }

    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            $table->dropColumn(['status', 'buffered_by_gateway', 'buffered_at']);
        });
    }
};
