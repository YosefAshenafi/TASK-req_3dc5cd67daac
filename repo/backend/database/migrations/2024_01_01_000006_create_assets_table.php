<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('mime_type', 100);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('duration')->nullable()->comment('seconds');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->unsignedBigInteger('play_count')->default(0);
            $table->string('thumbnail_160', 500)->nullable();
            $table->string('thumbnail_480', 500)->nullable();
            $table->string('thumbnail_960', 500)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('play_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
