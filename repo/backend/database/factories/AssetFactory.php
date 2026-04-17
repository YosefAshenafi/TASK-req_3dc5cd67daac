<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg', 'application/pdf'];

        return [
            'title'             => fake()->sentence(4),
            'description'       => fake()->paragraph(),
            'mime'              => fake()->randomElement($mimes),
            'duration_seconds'  => fake()->optional()->numberBetween(10, 3600),
            'size_bytes'        => fake()->numberBetween(1024, 50 * 1024 * 1024),
            'file_path'         => 'media/' . fake()->uuid() . '.bin',
            'fingerprint_sha256' => fake()->sha256(),
            'status'            => 'ready',
            'uploaded_by'       => User::factory(),
        ];
    }

    /**
     * Indicate the asset is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    /**
     * Indicate the asset has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
