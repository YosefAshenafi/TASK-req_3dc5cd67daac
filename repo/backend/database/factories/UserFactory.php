<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role'     => 'user',
            'email_enc' => null,
        ];
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the user is a technician.
     */
    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'technician',
        ]);
    }

    /**
     * Indicate that the user account is frozen.
     */
    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'frozen_until' => now()->addHours(24),
        ]);
    }

    /**
     * Indicate that the user account is blacklisted.
     */
    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'blacklisted_at' => now(),
        ]);
    }
}
