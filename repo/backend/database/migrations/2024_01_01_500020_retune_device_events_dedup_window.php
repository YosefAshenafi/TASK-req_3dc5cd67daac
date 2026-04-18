<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the permanent (device_id, idempotency_key) unique constraint with a
 * query-friendly non-unique index. Dedup is now enforced in the DeviceController against a
 * 7-day window, not for the lifetime of the table. Keeping a plain index lets the
 * per-request "is this a duplicate within the window?" lookup stay fast without locking
 * the ingestion path into forever-reject semantics.
 *
 * MySQL won't let us drop the unique index on (device_id, idempotency_key) while the
 * device_events.device_id foreign key still depends on it for the implicit index, so we
 * first add a standalone non-unique index on device_id to preserve the FK and our
 * dedup-lookup index, then drop the unique constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add the replacement FK-supporting index and the dedup-lookup index.
        Schema::table('device_events', function (Blueprint $table) {
            if (! $this->indexExists('device_events', 'device_events_device_id_idx')) {
                $table->index('device_id', 'device_events_device_id_idx');
            }

            if (! $this->indexExists('device_events', 'device_events_dedup_lookup_idx')) {
                $table->index(
                    ['device_id', 'idempotency_key', 'occurred_at'],
                    'device_events_dedup_lookup_idx'
                );
            }
        });

        // Step 2: now that the FK has an alternative index to lean on, drop the unique.
        Schema::table('device_events', function (Blueprint $table) {
            try {
                $table->dropUnique('device_events_device_id_idempotency_key_unique');
            } catch (\Throwable) {
                try {
                    $table->dropUnique(['device_id', 'idempotency_key']);
                } catch (\Throwable) {
                    // Already dropped — fine.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_events', function (Blueprint $table) {
            try {
                $table->unique(['device_id', 'idempotency_key']);
            } catch (\Throwable) {
                // Already unique.
            }
        });

        Schema::table('device_events', function (Blueprint $table) {
            try {
                $table->dropIndex('device_events_dedup_lookup_idx');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('device_events_device_id_idx');
            } catch (\Throwable) {
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $rows = DB::select(
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
