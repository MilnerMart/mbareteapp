<?php

namespace Database\Seeders;

use App\Models\Muscle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MuscleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Muscle::factory()->count(5)->create();
    }
}
