<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $mimeTypes = ['audio/mpeg', 'audio/mpeg', 'video/mp4', 'image/jpeg', 'application/pdf'];
        $mime = fake()->randomElement($mimeTypes);

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'tags' => fake()->randomElements(['announcement', 'safety', 'training', 'music', 'notice'], 2),
            'mime_type' => $mime,
            'file_path' => 'media/' . fake()->uuid() . '.bin',
            'file_size' => fake()->numberBetween(1024, 1024 * 1024 * 10),
            'duration' => in_array($mime, ['audio/mpeg', 'video/mp4']) ? fake()->numberBetween(5, 300) : null,
            'status' => 'approved',
            'uploaded_by' => User::factory(),
            'play_count' => fake()->numberBetween(0, 500),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }
}
