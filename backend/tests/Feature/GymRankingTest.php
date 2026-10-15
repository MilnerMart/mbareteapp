<?php

namespace Tests\Feature;

use App\Models\RoutineExerciseLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class GymRankingTest extends TestCase
{
    use InteractsWithMuscleApi;

    private int $gymId;

    private array $exerciseIdList;

    public function test_season_ranking_counts_assigned_routines_closes_with_awards_and_respects_visibility(): void
    {
        [$coachToken, $coachId] = $this->approvedTrainer('coach@example.com');
        $this->gymId = $this->createGym('Gym Ranking', $coachId);
        // la temporada 1 la inicio el entrenador hace 35 dias y termino hace 6
        $this->insertSeason(1, $this->dayAgo(35), $this->dayAgo(6));
        $this->exerciseIdList = DB::table('exercises')->orderBy('id')->limit(2)->pluck('id')->all();
        [$anaToken, $anaId] = $this->member('ana@example.com', true);
        [$benToken, $benId] = $this->member('ben@example.com', true);
        [$caroToken, $caroId] = $this->member('caro@example.com', false);
        $outsiderToken = $this->register('outsider@example.com', 'trainee-role')->json('data.token');

        $coachRoutine = $this->createRoutine($coachToken, 'Rutina del coach');
        foreach ([$anaId, $benId, $caroId] as $studentId) {
            $this->actingWithToken($coachToken)->postJson('/api/v1/routines/'.$coachRoutine.'/assign', ['user_id' => $studentId])->assertCreated();
        }
        $anaOwnRoutine = $this->createRoutine($anaToken, 'Rutina propia de Ana');

        // Ana: 3 dias seguidos completos con peso en la primera mitad; lo de su rutina propia no suma
        foreach ([30, 29, 28] as $daysAgo) {
            $this->insertLog($anaId, $coachRoutine, $this->exerciseIdList[0], $daysAgo, 50);
            $this->insertLog($anaId, $coachRoutine, $this->exerciseIdList[1], $daysAgo, 50);
        }
        $this->insertLog($anaId, $anaOwnRoutine, $this->exerciseIdList[0], 10, 100);
        // Ben: un dia en la segunda mitad y sin terminar la rutina; Caro no se muestra, no participa
        $this->insertLog($benId, $coachRoutine, $this->exerciseIdList[0], 15, 10);
        $this->insertLog($caroId, $coachRoutine, $this->exerciseIdList[0], 15, 200);
        // despues de que termino la temporada 1 (no cuenta para ninguna)
        $this->insertLog($benId, $coachRoutine, $this->exerciseIdList[0], 3, 10);

        $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertForbidden();
        $this->actingWithToken($outsiderToken)->getJson($this->rankingUrl())->assertForbidden();
        $this->actingWithToken($anaToken)->putJson($this->rankingUrl(), ['is_public' => true])->assertForbidden();
        $this->actingWithToken($coachToken)->putJson($this->rankingUrl(), ['is_public' => true])->assertOk()->assertJsonPath('data.isRankingPublic', true);
        // mirar el ranking no cierra la temporada vencida: la cierra la regla programada
        $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertOk()->assertJsonPath('data.recap', null);
        $this->artisan('rules:run', ['rule' => 'ranking'])->assertSuccessful();

        $ranking = $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertOk();
        $ranking->assertJsonPath('data.current', null)
            ->assertJsonPath('data.closedSeasonNumbers', [1])
            ->assertJsonPath('data.recap.number', 1)
            ->assertJsonPath('data.openRecap', true);
        $recap = $ranking->json('data.recap');
        $this->assertSame([$anaId, $benId], array_column($recap['standings'], 'userId'));
        $this->assertSame([3, 3, 3, 2], [$recap['standings'][0]['trainingDays'], $recap['standings'][0]['bestStreak'],
            $recap['standings'][0]['completedSessions'], $recap['standings'][1]['position']]);
        $this->assertSame(0, $recap['standings'][1]['completedSessions']);
        $this->assertSame(12000, $recap['standings'][0]['kilos']);
        $this->assertTrue($recap['standings'][0]['isPodium'] && $recap['standings'][1]['isPodium']);
        $this->assertSame(['consistency' => $anaId, 'streak' => $anaId, 'strength' => $anaId, 'endurance' => $anaId, 'comeback' => $benId],
            array_column($recap['awards'], 'userId', 'slug'));
        $this->assertSame(1, $recap['viewer']['standing']['position']);
        $this->assertEqualsCanonicalizing(['consistency', 'streak', 'strength', 'endurance'], $recap['viewer']['awardSlugs']);

        // el desglose aparece una sola vez solo; despues se abre a pedido
        $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertOk()->assertJsonPath('data.openRecap', false);
        $this->actingWithToken($anaToken)->getJson($this->rankingUrl().'?season=1')->assertOk()->assertJsonPath('data.openRecap', true);

        $benNotice = collect($this->actingWithToken($benToken)->getJson('/api/v1/me/notifications')->json('data.items'))->firstWhere('type', 'season-closed');
        $this->assertSame(['gymName' => 'Gym Ranking', 'season' => 1, 'position' => 2], $benNotice['data']);
        $coachNotice = collect($this->actingWithToken($coachToken)->getJson('/api/v1/me/notifications')->json('data.items'))->firstWhere('type', 'season-closed');
        $this->assertTrue($coachNotice['data']['isOwner']);
        $this->assertSame(0, DB::table('user_notifications')->where('user_id', $caroId)->where('type', 70)->count());
        $this->assertSame(1, DB::table('gym_seasons')->where('gym_id', $this->gymId)->count());

        // el resultado queda en la actividad del calendario de cada uno (Caro no participo, el entrenador ve el cierre)
        $month = substr(RoutineExerciseLog::today(), 0, 7);
        $anaResult = collect($this->actingWithToken($anaToken)->getJson('/api/v1/me/calendar?month='.$month)->json('data.actions'))
            ->firstWhere('action', 'season-result');
        $this->assertSame(['gym', $this->gymId, 1, 1], [$anaResult['model'], $anaResult['entityId'], $anaResult['data']['season'], $anaResult['data']['position']]);
        $this->assertEqualsCanonicalizing(['consistency', 'streak', 'strength', 'endurance'], $anaResult['data']['awards']);
        $benResult = collect($this->actingWithToken($benToken)->getJson('/api/v1/me/calendar?month='.$month)->json('data.actions'))
            ->firstWhere('action', 'season-result');
        $this->assertSame([2, ['comeback']], [$benResult['data']['position'], $benResult['data']['awards']]);
        $this->assertSame(0, DB::table('entity_logs')->where('user_id', $caroId)->where('action', 200)->count());
        $coachResult = DB::table('entity_logs')->where('user_id', $coachId)->where('action', 200)->value('data');
        $this->assertSame(['name' => 'Gym Ranking', 'season' => 1, 'isOwner' => true, 'participantCount' => 2], json_decode($coachResult, true));

        // el entrenador inicia la temporada 2 hoy: solo cuenta lo de hoy en adelante
        $this->actingWithToken($coachToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(0), 'ends_on' => $this->dayAgo(-29)])
            ->assertCreated()->assertJsonPath('data.number', 2)->assertJsonPath('data.status', 'active');
        $this->assertSame(1, DB::table('entity_logs')->where('user_id', $coachId)->where('action', 180)->count());
        $this->insertLog($benId, $coachRoutine, $this->exerciseIdList[0], 0, 10);
        $current = $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertOk()
            ->assertJsonPath('data.current.number', 2)
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.current.days', 30)
            ->assertJsonPath('data.current.daysLeft', 30)
            ->json('data.current');
        $this->assertSame([$benId, $anaId], array_column($current['standings'], 'userId'));
        $this->assertSame([1, 0], array_column($current['standings'], 'trainingDays'));
        $this->assertSame([1, 0], array_column($current['standings'], 'currentStreak'));
        $this->assertSame($current['standings'][0]['points'], $current['standings'][0]['todayPoints']);
        $this->assertSame(0, $recap['standings'][0]['currentStreak']);
        $this->assertFalse($current['standings'][1]['isPodium']);

        $this->actingWithToken($caroToken)->getJson($this->rankingUrl())->assertOk()
            ->assertJsonPath('data.current.viewer.reason', 'private')
            ->assertJsonPath('data.recap.viewer.reason', 'absent');
        $this->actingWithToken($coachToken)->getJson($this->rankingUrl())->assertOk()
            ->assertJsonPath('data.canManage', true)
            ->assertJsonPath('data.current.viewer.reason', 'owner');

        // el admin lee las mismas acciones en el registro de cada usuario
        $adminToken = $this->login(static::adminEmail);
        $this->assertContains('season-result', array_column($this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$anaId.'/logs')->assertOk()->json('data.items'), 'action'));
        $this->assertEqualsCanonicalizing(['season-started', 'season-result'], array_values(array_intersect(['season-started', 'season-result'],
            array_column($this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$coachId.'/logs')->assertOk()->json('data.items'), 'action'))));

        $this->actingWithToken($coachToken)->putJson($this->rankingUrl(), ['is_public' => false])->assertOk();
        $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertForbidden();
        $this->actingWithToken($this->login(static::adminEmail))->getJson($this->rankingUrl())->assertOk()
            ->assertJsonPath('data.canManage', true)
            ->assertJsonPath('data.current.viewer.reason', 'guest')
            ->assertJsonPath('data.recap.number', 1);
    }

    public function test_only_the_trainer_starts_seasons_and_after_starting_only_the_end_date_changes(): void
    {
        [$coachToken, $coachId] = $this->approvedTrainer('coach@example.com');
        $this->gymId = $this->createGym('Gym Fechas', $coachId);
        DB::table('gym_entities')->where('id', $this->gymId)->update(['is_ranking_public' => true]);
        [$anaToken] = $this->member('ana@example.com', true);

        // sin temporada iniciada no hay ranking en curso, ni al mirarlo ni desde la campanita
        $this->actingWithToken($anaToken)->getJson('/api/v1/me/notifications')->assertOk();
        $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertOk()->assertJsonPath('data.current', null);
        $this->assertSame(0, DB::table('gym_seasons')->count());

        $this->actingWithToken($anaToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(0), 'ends_on' => $this->dayAgo(-10)])->assertForbidden();
        $this->actingWithToken($coachToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(1), 'ends_on' => $this->dayAgo(-10)])->assertStatus(400);
        $this->actingWithToken($coachToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(-5), 'ends_on' => $this->dayAgo(-14)])->assertCreated();
        $this->actingWithToken($coachToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(-5), 'ends_on' => $this->dayAgo(-1)])->assertStatus(400);
        $this->actingWithToken($anaToken)->getJson($this->rankingUrl())->assertOk()
            ->assertJsonPath('data.current.status', 'scheduled')
            ->assertJsonPath('data.current.daysToStart', 5)
            ->assertJsonPath('data.current.standings', []);

        // programada: se pueden cambiar las dos fechas, dentro de las reglas
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['starts_on' => $this->dayAgo(-3), 'ends_on' => $this->dayAgo(-2)])->assertStatus(400);
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['starts_on' => $this->dayAgo(-2), 'ends_on' => $this->dayAgo(-400)])->assertStatus(400);
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['starts_on' => $this->dayAgo(-2), 'ends_on' => $this->dayAgo(-20)])
            ->assertOk()->assertJsonPath('data.startsOn', $this->dayAgo(-2))->assertJsonPath('data.endsOn', $this->dayAgo(-20));

        // ya empezo: el inicio queda fijo y el fin no puede quedar en el pasado
        DB::table('gym_seasons')->update(['starts_on' => $this->dayAgo(3)]);
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['starts_on' => $this->dayAgo(0), 'ends_on' => $this->dayAgo(-20)])->assertStatus(400);
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['ends_on' => $this->dayAgo(1)])->assertStatus(400);
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['ends_on' => $this->dayAgo(0)])
            ->assertOk()->assertJsonPath('data.startsOn', $this->dayAgo(3))->assertJsonPath('data.status', 'active');
        $this->actingWithToken($coachToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(-1), 'ends_on' => $this->dayAgo(-9)])->assertStatus(400);

        // terminada: ya no se modifica y la siguiente cierra la anterior al iniciarse
        DB::table('gym_seasons')->update(['ends_on' => $this->dayAgo(1)]);
        $this->actingWithToken($coachToken)->putJson($this->currentSeasonUrl(), ['ends_on' => $this->dayAgo(-5)])->assertStatus(400);
        $this->actingWithToken($anaToken)->getJson('/api/v1/me/notifications')->assertOk();
        $this->assertNull(DB::table('gym_seasons')->where('number', 1)->value('results'));
        $this->actingWithToken($coachToken)->postJson($this->seasonUrl(), ['starts_on' => $this->dayAgo(0), 'ends_on' => $this->dayAgo(-6)])
            ->assertCreated()->assertJsonPath('data.number', 2);
        $this->assertNull(DB::table('gym_seasons')->where('number', 1)->value('results'));
        $this->artisan('rules:run', ['rule' => 'ranking'])->assertSuccessful();
        $this->assertNotNull(DB::table('gym_seasons')->where('number', 1)->value('results'));
        $this->assertSame(1, DB::table('user_notifications')->where('type', 70)->where('ref_id', $this->gymId)->where('data', 'like', '%"isOwner":true%')->count());
    }

    private function rankingUrl(): string
    {
        return '/api/v1/entities/gym/'.$this->gymId.'/ranking';
    }

    private function seasonUrl(): string
    {
        return '/api/v1/entities/gym/'.$this->gymId.'/seasons';
    }

    private function currentSeasonUrl(): string
    {
        return $this->seasonUrl().'/current';
    }

    private function insertSeason(int $number, string $startsOn, string $endsOn): void
    {
        DB::table('gym_seasons')->insert([
            'gym_id' => $this->gymId, 'number' => $number, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function dayAgo(int $days): string
    {
        return CarbonImmutable::parse(RoutineExerciseLog::today())->subDays($days)->toDateString();
    }

    private function approvedTrainer(string $email): array
    {
        $register = $this->register($email, 'trainer-role')->assertCreated();
        $this->actingWithToken($this->login(static::adminEmail))
            ->postJson('/api/v1/tickets/'.$register->json('data.tickets.0.id').'/approve')->assertOk();
        return [$this->login($email), $register->json('data.user.id')];
    }

    private function createGym(string $name, int $ownerId): int
    {
        return DB::table('gym_entities')->insertGetId([
            'name' => $name, 'slug' => str($name)->slug(), 'owner_id' => $ownerId, 'status' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(string $email, bool $isPublic): array
    {
        $register = $this->register($email, 'trainee-role')->assertCreated();
        $userId = $register->json('data.user.id');
        DB::table('gym_users')->insert(['gym_id' => $this->gymId, 'user_id' => $userId, 'is_public' => $isPublic, 'created_at' => now(), 'updated_at' => now()]);
        return [$register->json('data.token'), $userId];
    }

    private function createRoutine(string $token, string $name): int
    {
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => $name, 'frequency' => 3, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        foreach ($this->exerciseIdList as $exerciseId) {
            $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId, 'sets' => 4, 'reps' => 10])
                ->assertCreated();
        }
        return $routineId;
    }

    private function insertLog(int $userId, int $routineId, int $exerciseId, int $daysAgo, float $weightKg): void
    {
        DB::table('routine_exercise_logs')->insert([
            'user_id' => $userId, 'routine_id' => $routineId, 'exercise_id' => $exerciseId, 'done_on' => $this->dayAgo($daysAgo),
            'sets' => 4, 'reps' => 10, 'weight_kg' => $weightKg, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
