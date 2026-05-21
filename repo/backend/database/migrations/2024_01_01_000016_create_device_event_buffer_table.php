<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_event_buffer', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('event_type', 100);
            $table->json('event_payload');
            $table->string('idempotency_key', 255);
            $table->unsignedInteger('sequence');
            $table->string('replay_audit_id', 255)->nullable();
            $table->enum('delivery_state', ['pending', 'sending', 'sent', 'failed'])->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices');

            // Dequeue and retry selection: state + due time per device
            $table->index(['device_id', 'delivery_state', 'next_retry_at'], 'idx_buffer_dequeue');
            // Eviction ordering: oldest pending entry per device
            $table->index(['device_id', 'id'], 'idx_buffer_eviction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_event_buffer');
    }
};
