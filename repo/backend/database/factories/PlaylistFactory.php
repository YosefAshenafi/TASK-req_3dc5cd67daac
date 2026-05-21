<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaylistFactory extends Factory
{
    protected $model = Playlist::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'share_code' => null,
        ];
    }

    public function withShareCode(): static
    {
        return $this->state(['share_code' => \Illuminate\Support\Str::random(8)]);
    }
}
