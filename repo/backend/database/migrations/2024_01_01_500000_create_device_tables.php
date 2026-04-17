<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('kind', 50);
            $table->string('label')->nullable();
            $table->bigInteger('last_sequence_no')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('device_id', 64);
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->bigInteger('sequence_no');
            $table->uuid('idempotency_key');
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->boolean('is_out_of_order')->default(false);
            $table->json('payload_json')->nullable();
            $table->unique(['device_id', 'idempotency_key']);
        });

        Schema::create('replay_audits', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64);
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('since_sequence_no');
            $table->bigInteger('until_sequence_no')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replay_audits');
        Schema::dropIfExists('device_events');
        Schema::dropIfExists('devices');
    }
};
