<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_search_index', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained('assets')->cascadeOnDelete();
            $table->text('search_text');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE asset_search_index ADD FULLTEXT INDEX ft_search_text (search_text)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_search_index');
    }
};
