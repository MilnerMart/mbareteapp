<?php

namespace Database\Factories;

use App\Models\Routine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Routine>
 */
class RoutineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $exercise = [1, 2, 3, 4, 5 , 6, 7, 8, 9];   
        $muscle = [1, 2, 3, 4, 5];
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'frecuency' => fake()->numberBetween(1, 3),
            'rest_between_exercises' => fake()->numberBetween(1, 3),
            'data' => [
                'exercise_list' => $exercise,
                'affected_muscles' => $muscle,
            ]
            
        ];
    }
}
