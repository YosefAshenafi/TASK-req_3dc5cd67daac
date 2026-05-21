<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username', 50)->unique();
            $table->text('email');
            $table->string('password');
            $table->enum('role', ['user', 'admin', 'technician'])->default('user');
            $table->enum('account_status', ['active', 'frozen', 'blacklisted'])->default('active');
            $table->timestamp('frozen_until')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
