<?php

namespace Database\Factories;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name('male'),
            'slug' => fake()->slug(2),
            'muscle_id' => fake()->numberBetween(1, 5),
            'description' => fake()->sentence(),
            'recommended_rest_time' => fake()->numberBetween(1, 5),
            'image' => fake()->imageUrl(),
            'video' => fake()->url(),
            'gif' => fake()->url()
        ];
    }
}
