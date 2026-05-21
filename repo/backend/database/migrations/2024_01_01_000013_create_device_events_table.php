<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('idempotency_key', 255);
            $table->unsignedBigInteger('sequence');
            $table->enum('status', ['received', 'late', 'buffered', 'duplicate'])->default('received');
            $table->string('replay_audit_id', 255)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['device_id', 'idempotency_key']);
            $table->index(['device_id', 'sequence']);
            $table->index(['device_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_events');
    }
};
