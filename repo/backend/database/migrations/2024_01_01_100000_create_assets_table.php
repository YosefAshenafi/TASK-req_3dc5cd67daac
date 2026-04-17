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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mime', 50);
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('size_bytes')->unsigned();
            $table->string('file_path');
            $table->char('fingerprint_sha256', 64);
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('asset_tags', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('tag', 50);
            $table->primary(['asset_id', 'tag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_tags');
        Schema::dropIfExists('assets');
    }
};
