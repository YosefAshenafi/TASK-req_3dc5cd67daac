<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_replay_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('audit_key', 255)->unique()->comment('Client-provided unique key for this replay batch');
            $table->foreignId('device_id')->constrained('devices');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triggered_at')->useCurrent();
            $table->string('scope', 255)->nullable()->comment('Event range or batch descriptor');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_replay_audits');
    }
};
