<?php

namespace Tests\Feature;

use App\Models\RoutineExerciseLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_training_unlocks_achievements_once_with_bell_notice_and_activity(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        [$userId, $token] = [$student->json('data.user.id'), $student->json('data.token')];
        $this->clearRestDays($userId);
        $exerciseId = DB::table('exercises')->value('id');
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => 'Fuerza', 'frequency' => 3, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId, 'sets' => 4, 'reps' => 10])
            ->assertCreated();
        // dos dias seguidos antes de hoy, con 600 kg cada uno (15 kg x 4 x 10)
        foreach ([2, 1] as $daysAgo) {
            DB::table('routine_exercise_logs')->insert([
                'user_id' => $userId, 'routine_id' => $routineId, 'exercise_id' => $exerciseId,
                'done_on' => CarbonImmutable::parse(RoutineExerciseLog::today())->subDays($daysAgo)->toDateString(),
                'sets' => 4, 'reps' => 10, 'weight_kg' => 15, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingWithToken($token)->putJson('/api/v1/routines/'.$routineId.'/done', ['exercises' => [['exercise_id' => $exerciseId, 'weight_kg' => 15]]])->assertOk();

        $inbox = $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertOk();
        $unlocked = collect($inbox->json('data.items'))->where('type', 'achievement')->pluck('data.achievement')->sort()->values()->all();
        $this->assertSame(['days-1', 'kilos-1500', 'kilos-500', 'streak-3'], $unlocked);

        $this->actingWithToken($token)->putJson('/api/v1/routines/'.$routineId.'/done', ['exercises' => []])->assertOk();
        $this->actingWithToken($token)->putJson('/api/v1/routines/'.$routineId.'/done', ['exercises' => [['exercise_id' => $exerciseId, 'weight_kg' => 15]]])->assertOk();
        $this->assertSame(4, DB::table('user_notifications')->where('user_id', $userId)->where('type', 60)->count());

        $profile = $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk();
        $streak3 = collect($profile->json('data.achievements'))->firstWhere('slug', 'streak-3');
        $streak7 = collect($profile->json('data.achievements'))->firstWhere('slug', 'streak-7');
        $this->assertTrue($streak3['isUnlocked']);
        $this->assertNotNull($streak3['unlockedAt']);
        $this->assertSame([false, 3, 7], [$streak7['isUnlocked'], $streak7['current'], $streak7['goal']]);

        $month = substr(RoutineExerciseLog::today(), 0, 7);
        $actions = $this->actingWithToken($token)->getJson('/api/v1/me/calendar?month='.$month)->json('data.actions');
        $this->assertContains('streak-3', array_column(array_column(array_filter($actions, fn($log) => $log['action'] === 'achievement'), 'data'), 'achievement'));
    }

    public function test_existing_history_unlocks_when_opening_own_profile_but_not_when_others_look(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        [$userId, $token] = [$student->json('data.user.id'), $student->json('data.token')];
        $this->clearRestDays($userId);
        $exerciseId = DB::table('exercises')->value('id');
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => 'Cardio', 'frequency' => 3, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        DB::table('routine_exercise_logs')->insert([
            'user_id' => $userId, 'routine_id' => $routineId, 'exercise_id' => $exerciseId, 'done_on' => '2026-01-10',
            'duration_minutes' => 70, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $otherToken = $this->register('other@example.com', 'trainee-role')->json('data.token');
        $this->actingWithToken($otherToken)->getJson('/api/v1/user/'.$userId)->assertOk();
        $this->assertSame(0, DB::table('user_achievements')->where('user_id', $userId)->count());

        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk();
        $this->assertEqualsCanonicalizing(['days-1', 'minutes-60'], DB::table('user_achievements')->where('user_id', $userId)->pluck('slug')->all());
    }
}
