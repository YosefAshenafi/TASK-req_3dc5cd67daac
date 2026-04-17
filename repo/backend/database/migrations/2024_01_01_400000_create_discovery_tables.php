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
        Schema::create('recommendation_candidates', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->decimal('score', 8, 4);
            $table->json('reason_tags_json');
            $table->timestamp('refreshed_at');
            $table->primary(['user_id', 'asset_id']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->boolean('enabled')->default(true);
            $table->text('reason')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('search_index', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete()->primary();
            $table->text('tokenized_title');
            $table->text('tokenized_body');
            $table->text('weight_tsv')->nullable();
            $table->fullText(['tokenized_title', 'tokenized_body']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_index');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('recommendation_candidates');
    }
};
