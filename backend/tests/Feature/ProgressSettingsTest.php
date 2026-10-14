<?php

namespace Tests\Feature;

use App\Models\ProgressSettings;
use App\Models\RoutineExerciseLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class ProgressSettingsTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_a_single_long_session_is_not_enough_to_leave_novice(): void
    {
        [$userId, $token, $routineId, $exerciseId] = $this->studentWithRoutine();
        $this->insertDay($userId, $routineId, $exerciseId, 5, 200);

        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk()
            ->assertJsonPath('data.progress.points', 60)
            ->assertJsonPath('data.progress.minutes', 200)
            ->assertJsonPath('data.progress.rank', 'novice');

        $this->insertDay($userId, $routineId, $exerciseId, 3, 200);
        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertJsonPath('data.progress.points', 120)
            ->assertJsonPath('data.progress.rank', 'novice');
        $this->insertDay($userId, $routineId, $exerciseId, 1, 45);
        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertJsonPath('data.progress.points', 175)
            ->assertJsonPath('data.progress.rank', 'beginner');
    }

    public function test_admin_configures_scoring_and_ranks_and_others_cannot(): void
    {
        [$userId, $token, $routineId, $exerciseId] = $this->studentWithRoutine();
        $this->insertDay($userId, $routineId, $exerciseId, 1, 200);
        $this->actingWithToken($token)->getJson('/api/v1/config/progress')->assertForbidden();
        $this->actingWithToken($token)->putJson('/api/v1/config/progress', [
            'scoring' => ProgressSettings::defaultScoring, 'ranks' => ProgressSettings::defaultRanks,
        ])->assertForbidden();

        $adminToken = $this->login(static::adminEmail);
        $settings = $this->actingWithToken($adminToken)->getJson('/api/v1/config/progress')->assertOk()
            ->assertJsonPath('data.scoring.maxPointsPerDay', 60)
            ->assertJsonPath('data.ranks.beginner', ['minPoints' => 150])
            ->json('data');

        $settings['scoring']['maxPointsPerDay'] = 500;
        $settings['ranks']['novice'] = ['minPoints' => 99];
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/progress', $settings)->assertOk()
            ->assertJsonPath('data.ranks.novice', ['minPoints' => 0])
            ->assertJsonPath('data.scoring.maxPointsPerDay', 500);
        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk()
            ->assertJsonPath('data.progress.points', 210)
            ->assertJsonPath('data.progress.rank', 'beginner');

        $settings['ranks']['steady']['minPoints'] = 100;
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/progress', $settings)->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cada rango tiene que pedir mas puntos que el anterior']);
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/progress', ['scoring' => ['pointsPerDay' => -1]])->assertUnprocessable();
    }

    private function studentWithRoutine(): array
    {
        $student = $this->register('student@example.com', 'trainee-role');
        $token = $student->json('data.token');
        $exerciseId = DB::table('exercises')->value('id');
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => 'Cardio', 'frequency' => 3, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        return [$student->json('data.user.id'), $token, $routineId, $exerciseId];
    }

    private function insertDay(int $userId, int $routineId, int $exerciseId, int $daysAgo, int $minutes): void
    {
        DB::table('routine_exercise_logs')->insert([
            'user_id' => $userId, 'routine_id' => $routineId, 'exercise_id' => $exerciseId,
            'done_on' => CarbonImmutable::parse(RoutineExerciseLog::today())->subDays($daysAgo)->toDateString(),
            'duration_minutes' => $minutes, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
