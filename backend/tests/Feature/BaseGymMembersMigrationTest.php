<?php

namespace Tests\Feature;

use App\Models\Gym;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class BaseGymMembersMigrationTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_users_left_out_of_the_base_gym_join_it_without_showing_up(): void
    {
        $migration = require database_path('migrations/2026_11_04_100000_add_missing_users_to_base_gym.php');
        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        $trainerId = $this->register('coach@example.com', 'trainer-role')->json('data.user.id');
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        $deletedId = $this->register('gone@example.com', 'trainee-role')->json('data.user.id');
        DB::table('gym_users')->where('gym_id', $baseGymId)->whereIn('user_id', [$trainerId, $deletedId])->delete();
        DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $studentId)->update(['is_public' => true]);
        DB::table('users')->where('id', $deletedId)->update(['status' => 1]);

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $trainerId)->count());
        $this->assertSame(0, (int)DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $trainerId)->value('is_public'));
        $this->assertSame(1, (int)DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $studentId)->value('is_public'));
        $this->assertFalse(DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $deletedId)->exists());
        $this->assertSame(0, DB::table('users')->where('status', '!=', 1)
            ->whereNotIn('id', DB::table('gym_users')->where('gym_id', $baseGymId)->pluck('user_id'))->count());
    }

    public function test_without_a_base_gym_nothing_happens(): void
    {
        $migration = require database_path('migrations/2026_11_04_100000_add_missing_users_to_base_gym.php');
        DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->update(['slug' => 'otro-gym']);
        $before = DB::table('gym_users')->count();

        $migration->up();

        $this->assertSame($before, DB::table('gym_users')->count());
    }
}
