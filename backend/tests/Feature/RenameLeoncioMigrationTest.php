<?php

namespace Tests\Feature;

use App\Models\Gym;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class RenameLeoncioMigrationTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_existing_leoncio_data_moves_to_chavuku_and_back(): void
    {
        $migration = require database_path('migrations/2026_10_30_100000_rename_leoncio_to_chavuku.php');
        DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->update(['slug' => 'leoncion-gym', 'name' => 'Leoncio Gym']);
        $ownGymId = DB::table('gym_entities')->insertGetId([
            'name' => 'Leoncio Gym', 'slug' => 'otro-gym', 'owner_id' => 1, 'status' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $muscleId = DB::table('muscles')->value('id');
        DB::table('muscles')->where('id', $muscleId)->update(['slug' => 'leoncio-calves']);
        $resourceId = DB::table('resources')->insertGetId([
            'name' => 'Leoncio Gym', 'slug' => 'leoncion-gym-image', 'url' => 'images/leoncioBiceps.png', 'kind' => 10,
            'model_id' => 40, 'owner_id' => $muscleId, 'status' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertSame('Chavuku Gym', DB::table('gym_entities')->where('slug', 'chavuku-gym')->value('name'));
        $this->assertSame('Chavuku Gym', DB::table('gym_entities')->where('id', $ownGymId)->value('name'));
        $this->assertSame('chavuku-calves', DB::table('muscles')->where('id', $muscleId)->value('slug'));
        $this->assertSame(['Chavuku Gym', 'chavuku-gym-image', 'images/chavukuBiceps.png'],
            array_values((array)DB::table('resources')->where('id', $resourceId)->first(['name', 'slug', 'url'])));
        $this->assertFileExists(base_path('../frontend/public/images/chavukuBiceps.png'));

        $migration->down();
        $this->assertSame('Leoncio Gym', DB::table('gym_entities')->where('slug', 'leoncion-gym')->value('name'));
        $this->assertSame('images/leoncioBiceps.png', DB::table('resources')->where('id', $resourceId)->value('url'));
    }
}
