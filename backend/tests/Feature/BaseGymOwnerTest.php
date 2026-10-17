<?php

namespace Tests\Feature;

use App\Models\Gym;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class BaseGymOwnerTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_migration_takes_the_owner_out_of_the_base_gym_students(): void
    {
        $migration = require database_path('migrations/2026_11_06_100000_remove_owner_from_base_gym_members.php');
        $baseGym = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->first();
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        DB::table('gym_users')->insertOrIgnore(['gym_id' => $baseGym->id, 'user_id' => $baseGym->owner_id, 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        $this->assertFalse(DB::table('gym_users')->where('gym_id', $baseGym->id)->where('user_id', $baseGym->owner_id)->exists());
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $baseGym->id)->where('user_id', $studentId)->exists());
    }

    public function test_owner_manages_the_base_gym_without_being_a_student(): void
    {
        $baseGym = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->first();
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        $adminToken = $this->login(static::adminEmail);

        $this->actingWithToken($adminToken)->getJson('/api/v1/me/gyms')->assertOk()->assertJsonCount(0, 'data');
        $this->actingWithToken($adminToken)->getJson('/api/v1/entities/gym/'.$baseGym->id)->assertOk()
            ->assertJsonPath('data.isBase', true)
            ->assertJsonPath('data.alumnsCount', 1);
        $this->actingWithToken($adminToken)->getJson('/api/v1/entities/gym/'.$baseGym->id.'/users')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $studentId);
    }

    public function test_students_cannot_be_removed_from_the_base_gym(): void
    {
        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');

        $this->actingWithToken($this->login(static::adminEmail))->deleteJson('/api/v1/entities/gym/'.$baseGymId.'/users/'.$studentId)
            ->assertStatus(400);
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $studentId)->exists());
    }

    public function test_new_base_gym_owner_stops_being_a_student_and_the_previous_one_becomes_one(): void
    {
        $baseGym = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->first();
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');

        $this->actingWithToken($this->login(static::adminEmail))->putJson('/api/v1/entities/gym/'.$baseGym->id, [
            'name' => $baseGym->name, 'slug' => $baseGym->slug, 'owner_id' => $studentId,
        ])->assertOk();

        $this->assertFalse(DB::table('gym_users')->where('gym_id', $baseGym->id)->where('user_id', $studentId)->exists());
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $baseGym->id)->where('user_id', $baseGym->owner_id)->exists());
    }
}
