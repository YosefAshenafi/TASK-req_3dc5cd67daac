<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'user',
            'account_status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function technician(): static
    {
        return $this->state(['role' => 'technician']);
    }

    public function frozen(): static
    {
        return $this->state([
            'account_status' => 'frozen',
            'frozen_until' => now()->addHours(72),
        ]);
    }

    public function blacklisted(): static
    {
        return $this->state(['account_status' => 'blacklisted']);
    }
}
