<?php

namespace Tests\Feature;

use App\Models\RoutineExerciseLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use InteractsWithMuscleApi {
        setUp as setUpMuscleApi;
    }

    protected function setUp(): void
    {
        $this->setUpMuscleApi();
        Http::preventStrayRequests();
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response([
                ['id' => 'Running_Treadmill', 'name' => 'Running, Treadmill', 'images' => ['Running_Treadmill/0.jpg', 'Running_Treadmill/1.jpg']],
            ]),
            'wger.de/*' => Http::response(['next' => null, 'results' => []]),
        ]);
    }

    public function test_profile_saves_goal_and_favorites_come_from_what_the_user_trains(): void
    {
        $owner = $this->register('atleta@example.com', 'trainee-role')->assertCreated();
        [$ownerId, $ownerToken] = [$owner->json('data.user.id'), $owner->json('data.token')];
        $runId = DB::table('exercises')->where('name', 'Correr en cinta')->value('id');
        $ropeId = DB::table('exercises')->where('name', 'Saltar la cuerda')->value('id');
        $cardioRoutine = $this->createRoutine($ownerToken, 'Cardio diario', [$runId, $ropeId]);
        $otherRoutine = $this->createRoutine($ownerToken, 'Otra rutina', [$ropeId]);

        $this->actingWithToken($ownerToken)->getJson('/api/v1/user/'.$ownerId)->assertOk()
            ->assertJsonPath('data.favorites', ['routine' => null, 'muscle' => null, 'exercise' => null]);

        // mas dias con la rutina de cardio que con la otra; la cuerda se hace mas veces que la cinta
        $this->insertLog($ownerId, $cardioRoutine, $runId, '2026-01-09');
        $this->insertLog($ownerId, $cardioRoutine, $runId, '2026-01-10');
        $this->insertLog($ownerId, $cardioRoutine, $ropeId, '2026-01-10');
        $this->insertLog($ownerId, $otherRoutine, $ropeId, '2026-01-11');
        $this->actingWithToken($ownerToken)->putJson('/api/v1/routines/'.$cardioRoutine.'/done', ['exercises' => [['exercise_id' => $ropeId, 'weight_kg' => 12.5]]])
            ->assertOk()->assertJsonPath('data.doneExerciseIds', [$ropeId]);
        $this->actingWithToken($ownerToken)->getJson('/api/v1/routines/'.$cardioRoutine)->assertOk()
            ->assertJsonPath('data.exercises.0.doneToday', false)
            ->assertJsonPath('data.exercises.1.doneToday', true)
            ->assertJsonPath('data.exercises.1.doneWeightKg', 12.5);

        $this->actingWithToken($ownerToken)->putJson('/api/v1/user/'.$ownerId.'/profile', $this->profileData([
            'name' => '  María   José ', 'goal' => 'hypertrophy', 'gender' => 'female',
        ]))->assertOk()
            ->assertJsonPath('data.name', 'María José')
            ->assertJsonPath('data.goal', 'hypertrophy')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.favorites.routine.name', 'Cardio diario')
            ->assertJsonPath('data.favorites.routine.exerciseCount', 2)
            ->assertJsonPath('data.favorites.muscle.name', 'Cardio')
            ->assertJsonPath('data.favorites.muscle.kind', 'activity')
            ->assertJsonPath('data.favorites.exercise.name', 'Saltar la cuerda');

        // se guarda la lista completa del dia: la cinta entra y la cuerda (que ya no viene) se borra
        $this->actingWithToken($ownerToken)->putJson('/api/v1/routines/'.$cardioRoutine.'/done', ['exercises' => [['exercise_id' => $runId]]])->assertOk();
        $this->assertSame([$runId], DB::table('routine_exercise_logs')->where('done_on', '>', '2026-01-11')->pluck('exercise_id')->map(fn($id) => (int)$id)->all());
        $this->actingWithToken($ownerToken)->putJson('/api/v1/routines/'.$cardioRoutine.'/done', ['exercises' => []])->assertOk();
        $this->assertSame(0, DB::table('routine_exercise_logs')->where('done_on', '>', '2026-01-11')->count());

        $other = $this->register('otro@example.com', 'trainee-role');
        $this->actingWithToken($other->json('data.token'))->getJson('/api/v1/user/'.$ownerId)->assertOk()
            ->assertJsonPath('data.favorites.routine', null)
            ->assertJsonPath('data.favorites.muscle.name', 'Cardio');
        $this->actingWithToken($other->json('data.token'))->putJson('/api/v1/routines/'.$cardioRoutine.'/done', ['exercises' => [['exercise_id' => $runId]]])
            ->assertForbidden();
        $this->actingWithToken($ownerToken)->putJson('/api/v1/routines/'.$otherRoutine.'/done', ['exercises' => [['exercise_id' => $runId]]])
            ->assertStatus(400);

        $this->actingWithToken($this->login(static::adminEmail))->getJson('/api/v1/user/'.$ownerId)->assertOk()
            ->assertJsonPath('data.favorites.routine.name', 'Cardio diario');
    }

    public function test_profile_rejects_invalid_values_and_other_users(): void
    {
        $owner = $this->register('atleta@example.com', 'trainee-role');
        [$ownerId, $ownerToken] = [$owner->json('data.user.id'), $owner->json('data.token')];
        $otherId = $this->register('otro@example.com', 'trainee-role')->json('data.user.id');

        $this->actingWithToken($ownerToken)->putJson('/api/v1/user/'.$ownerId.'/profile', $this->profileData(['goal' => 'ser-gigante']))
            ->assertUnprocessable()->assertJsonValidationErrors('goal');
        $this->actingWithToken($ownerToken)->putJson('/api/v1/user/'.$ownerId.'/profile', $this->profileData(['gender' => 'otro']))
            ->assertUnprocessable()->assertJsonValidationErrors('gender');
        $this->actingWithToken($ownerToken)->putJson('/api/v1/user/'.$ownerId.'/profile', $this->profileData(['name' => 'juan@mail.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingWithToken($ownerToken)->putJson('/api/v1/user/'.$otherId.'/profile', $this->profileData())
            ->assertForbidden();
    }

    public function test_level_comes_from_points_for_days_streak_minutes_and_kilos(): void
    {
        $user = $this->register('atleta@example.com', 'trainee-role');
        [$userId, $token] = [$user->json('data.user.id'), $user->json('data.token')];
        $runId = DB::table('exercises')->where('name', 'Correr en cinta')->value('id');
        $walkId = DB::table('exercises')->where('name', 'Caminata en cinta')->value('id');
        $ropeId = DB::table('exercises')->where('name', 'Saltar la cuerda')->value('id');
        $routineId = $this->createRoutine($token, 'Mixta', [$runId, $walkId, $ropeId]);
        $today = CarbonImmutable::parse(RoutineExerciseLog::today());

        // hace 5 dias: 20 min de cinta. Ayer: 10 min de caminata y la cuerda como fuerza (3x10 con 50 kg, 60 s de descanso).
        // Hoy: 10 min de cinta. Ayer y hoy son seguidos: racha de 2.
        $this->insertLog($userId, $routineId, $runId, $today->subDays(5)->toDateString(), ['duration_minutes' => 20]);
        $this->insertLog($userId, $routineId, $walkId, $today->subDay()->toDateString(), ['duration_minutes' => 10]);
        $this->insertLog($userId, $routineId, $ropeId, $today->subDay()->toDateString(), ['sets' => 3, 'reps' => 10, 'weight_kg' => 50]);
        $this->insertLog($userId, $routineId, $runId, $today->toDateString(), ['duration_minutes' => 10]);

        // 3 dias x 10 + 1 dia de racha x 5 + (20 + 10 + 4 + 10) min + 1500 kg / 100 = 30 + 5 + 44 + 15
        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk()
            ->assertJsonPath('data.progress.points', 94)
            ->assertJsonPath('data.progress.trainingDays', 3)
            ->assertJsonPath('data.progress.currentStreak', 2)
            ->assertJsonPath('data.progress.bestStreak', 2)
            ->assertJsonPath('data.progress.minutes', 44)
            ->assertJsonPath('data.progress.kilos', 1500)
            ->assertJsonPath('data.progress.rank', 'novice')
            ->assertJsonPath('data.progress.nextRank', 'beginner')
            ->assertJsonPath('data.progress.nextRankPoints', 150)
            ->assertJsonPath('data.progress.progressPercent', 62);

        // una sesion larga no alcanza: cada dia suma como mucho 60 puntos (hace 5 dias: 10 + 80 min = 90 -> 60)
        $this->insertLog($userId, $routineId, $walkId, $today->subDays(5)->toDateString(), ['duration_minutes' => 60]);
        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk()
            ->assertJsonPath('data.progress.points', 124)
            ->assertJsonPath('data.progress.rank', 'novice');

        // otro dia mas de entrenamiento y llega
        $this->insertLog($userId, $routineId, $walkId, $today->subDays(3)->toDateString(), ['duration_minutes' => 30]);
        $this->actingWithToken($token)->getJson('/api/v1/user/'.$userId)->assertOk()
            ->assertJsonPath('data.progress.points', 164)
            ->assertJsonPath('data.progress.rank', 'beginner')
            ->assertJsonPath('data.progress.nextRank', 'steady');
    }

    private function createRoutine(string $token, string $name, array $exerciseIdList): int
    {
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => $name, 'frequency' => 3, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        foreach ($exerciseIdList as $exerciseId) {
            $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId, 'duration_minutes' => 10])
                ->assertCreated();
        }
        return $routineId;
    }

    private function insertLog(int $userId, int $routineId, int $exerciseId, string $day, array $values = []): void
    {
        DB::table('routine_exercise_logs')->insert($values + [
            'user_id' => $userId, 'routine_id' => $routineId, 'exercise_id' => $exerciseId, 'done_on' => $day,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function profileData(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Atleta',
            'last_name' => 'Prueba',
            'age' => 30,
            'height' => 175,
            'weight' => 80,
            'goal' => null,
            'gender' => null,
        ];
    }
}
