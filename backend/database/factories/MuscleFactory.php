<?php

namespace Database\Factories;

use App\Models\Muscle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Muscle>
 */
class MuscleFactory extends Factory
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
            'recommended_rest_days' => fake()->numberBetween(1, 3),
            'description' => fake()->sentence(),
            'image_url' => fake()->imageUrl()
        ];
    }
}
