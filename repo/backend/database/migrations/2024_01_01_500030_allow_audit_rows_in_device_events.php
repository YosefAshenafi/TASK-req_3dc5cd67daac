<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow duplicate and too_old ingestion attempts to be persisted as audit rows in
 * `device_events` so the technician console can show the full history of every attempt
 * (not just accepted/out-of-order side-effecting events).
 *
 * The existing schema requires non-null `sequence_no`, `received_at`, and a non-negative
 * sequence number; audit rows for rejected attempts share those constraints. Nothing
 * structural changes here — we add an index to make audit-status queries fast and a
 * comment-only migration so the intent is discoverable via `artisan migrate:status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            if (! $this->indexExists('device_events', 'device_events_status_idx')) {
                $table->index(['device_id', 'status'], 'device_events_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            try {
                $table->dropIndex('device_events_status_idx');
            } catch (\Throwable) {
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $rows = \Illuminate\Support\Facades\DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() '
                . 'AND table_name = ? AND index_name = ? LIMIT 1',
                [$table, $indexName]
            );
            return count($rows) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
