<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('replay_audit_fk')->nullable()->after('replay_audit_id')
                ->comment('FK to device_replay_audits.id for relational integrity');
            $table->foreign('replay_audit_fk')
                ->references('id')
                ->on('device_replay_audits')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table): void {
            $table->dropForeign(['replay_audit_fk']);
            $table->dropColumn('replay_audit_fk');
        });
    }
};
