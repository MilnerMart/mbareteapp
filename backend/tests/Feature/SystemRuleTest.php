<?php

namespace Tests\Feature;

use App\Core\CoreModel;
use App\Models\RoutineExerciseLog;
use App\SystemRules\RankingSystemRule;
use App\SystemRules\SystemRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FailingSystemRule;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class SystemRuleTest extends TestCase
{
    use InteractsWithMuscleApi;

    private const rankingRuleId = CoreModel::gymSeasonModelId;

    public function test_admin_sees_and_configures_the_rules_and_others_cannot(): void
    {
        $traineeToken = $this->register('ana@example.com', 'trainee-role')->json('data.token');
        $this->actingWithToken($traineeToken)->getJson('/api/v1/config/rules')->assertForbidden();
        $this->actingWithToken($traineeToken)->postJson('/api/v1/config/rules/'.self::rankingRuleId.'/run')->assertForbidden();

        $adminToken = $this->login(static::adminEmail);
        $ruleList = $this->actingWithToken($adminToken)->getJson('/api/v1/config/rules')->assertOk()->json('data');
        $this->assertSame(['reminders', 'ranking'], array_column($ruleList, 'slug'));
        $this->assertSame(['hourly', '00:00', 15], [$ruleList[0]['frequency'], $ruleList[0]['runAt'], $ruleList[0]['retryMinutes']]);
        $rule = $ruleList[1];
        $this->assertSame([self::rankingRuleId, 'ranking', true, 'daily', '00:05', 15, 'America/Asuncion', []],
            [$rule['ruleModelId'], $rule['slug'], $rule['isEnabled'], $rule['frequency'], $rule['runAt'], $rule['retryMinutes'], $rule['timezone'], $rule['runs']]);
        $this->assertTrue(CarbonImmutable::parse($rule['nextRunAt'])->isFuture());

        $this->actingWithToken($traineeToken)->putJson('/api/v1/config/rules', $this->rulesPayload(false, 'daily', '03:30'))->assertForbidden();
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/rules', $this->rulesPayload(true, 'weekly', '03:00'))
            ->assertUnprocessable()->assertJsonValidationErrors('rules.'.self::rankingRuleId.'.frequency');
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/rules', $this->rulesPayload(true, 'daily', '25:00'))
            ->assertUnprocessable()->assertJsonValidationErrors('rules.'.self::rankingRuleId.'.run_at');
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/rules', $this->rulesPayload(true, 'daily', '03:00', 2000))
            ->assertUnprocessable()->assertJsonValidationErrors('rules.'.self::rankingRuleId.'.retry_minutes');
        $this->actingWithToken($adminToken)->putJson('/api/v1/config/rules', ['rules' => [12345 => ['is_enabled' => true, 'frequency' => 'daily', 'run_at' => '03:00', 'retry_minutes' => 5]]])
            ->assertNotFound();
        $updated = collect($this->actingWithToken($adminToken)->putJson('/api/v1/config/rules', $this->rulesPayload(false, 'daily', '03:30', 30))
            ->assertOk()->json('data'))->firstWhere('slug', 'ranking');
        $nextRunAt = CarbonImmutable::parse($updated['nextRunAt'])->setTimezone('America/Asuncion');
        $this->assertSame([false, '03:30', 30, '03:30'], [$updated['isEnabled'], $updated['runAt'], $updated['retryMinutes'], $nextRunAt->format('H:i')]);
        $this->assertTrue($nextRunAt->isFuture() && $nextRunAt->lessThanOrEqualTo(now()->addDay()));
    }

    public function test_next_run_is_the_next_time_in_paraguay(): void
    {
        $after = CarbonImmutable::parse('2026-10-14 10:00', 'America/Asuncion');

        $this->assertSame('2026-10-15 00:05', SystemRule::buildNextRunAt('daily', '00:05', $after)->setTimezone('America/Asuncion')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-14 12:30', SystemRule::buildNextRunAt('daily', '12:30', $after)->setTimezone('America/Asuncion')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-14 10:15', SystemRule::buildNextRunAt('hourly', '00:15', $after)->setTimezone('America/Asuncion')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-14 11:00', SystemRule::buildNextRunAt('hourly', '07:00', $after)->setTimezone('America/Asuncion')->format('Y-m-d H:i'));
        $this->assertSame('UTC', SystemRule::buildNextRunAt('daily', '00:05', $after)->getTimezone()->getName());
    }

    public function test_ranking_rule_runs_when_due_closes_seasons_and_announces_starts_only_once(): void
    {
        [$coachToken, $coachId] = $this->approvedTrainer('coach@example.com');
        $endedGymId = $this->createGym('Gym Cerrado', $coachId);
        $startingGymId = $this->createGym('Gym Arranca', $coachId);
        $anaId = $this->member('ana@example.com', [$endedGymId, $startingGymId], true);
        $caroId = $this->member('caro@example.com', [$startingGymId], false);
        $this->insertSeason($endedGymId, 1, $this->dayAgo(10), $this->dayAgo(1));
        $this->insertSeason($startingGymId, 1, $this->dayAgo(0), $this->dayAgo(-13));

        $this->artisan('rules:run')->assertSuccessful();
        $this->assertSame(0, DB::table('system_rule_runs')->count());
        $this->assertNull(DB::table('gym_seasons')->where('gym_id', $endedGymId)->value('results'));

        $this->rankingRule()->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('rules:run')->expectsOutputToContain('ranking: completed')->assertSuccessful();

        $run = DB::table('system_rule_runs')->first();
        $this->assertSame([self::rankingRuleId, 30, 0], [(int)$run->rule_model_id, (int)$run->status, (int)$run->is_manual]);
        $this->assertSame(['gyms' => 2, 'closedSeasons' => 1, 'startNotices' => 1], json_decode($run->summary, true));
        $this->assertNotNull(DB::table('gym_seasons')->where('gym_id', $endedGymId)->value('results'));
        $this->assertSame(1, DB::table('user_notifications')->where('user_id', $anaId)->where('type', 80)->where('ref_id', $startingGymId)->count());
        $this->assertSame(0, DB::table('user_notifications')->where('user_id', $caroId)->count());
        $this->assertTrue(CarbonImmutable::parse($this->rankingRule()->value('next_run_at'))->isFuture());

        // ya le toco: no vuelve a correr hasta su proximo horario, y forzada no repite avisos ni cierres
        $this->artisan('rules:run')->assertSuccessful();
        $this->assertSame(1, DB::table('system_rule_runs')->count());
        $this->artisan('rules:run', ['rule' => 'ranking'])->assertSuccessful();
        $this->assertSame(['gyms' => 0, 'closedSeasons' => 0, 'startNotices' => 0], json_decode(DB::table('system_rule_runs')->orderByDesc('id')->value('summary'), true));
        $this->assertSame(1, DB::table('user_notifications')->where('user_id', $anaId)->where('type', 80)->count());

        // desactivada no corre aunque haya vencido
        $this->rankingRule()->update(['is_enabled' => false, 'next_run_at' => now()->subMinute()]);
        $this->artisan('rules:run')->assertSuccessful();
        $this->assertSame(2, DB::table('system_rule_runs')->count());

        $this->actingWithToken($this->login(static::adminEmail))->getJson('/api/v1/config/rules')->assertOk()
            ->assertJsonPath('data.1.runs.0.status', 'completed')
            ->assertJsonPath('data.1.runs.0.isManual', true)
            ->assertJsonPath('data.1.runs.1.summary.closedSeasons', 1);
    }

    public function test_failures_and_interrupted_runs_are_recorded(): void
    {
        config(['system_rules.rules' => [RankingSystemRule::class, FailingSystemRule::class]]);
        $this->artisan('rules:run')->assertSuccessful();
        $this->assertSame(2, DB::table('system_rules')->count());
        DB::table('system_rule_runs')->insert(['rule_model_id' => self::rankingRuleId, 'status' => 20, 'is_manual' => false,
            'started_at' => now()->subHours(2), 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)]);
        DB::table('system_rules')->where('rule_model_id', 999)->update(['next_run_at' => now()->subMinute()]);

        $this->artisan('rules:run')->expectsOutputToContain('failing: failed')->expectsOutputToContain('failing: reintenta')->assertFailed();

        $failed = DB::table('system_rule_runs')->where('rule_model_id', 999)->first();
        $this->assertSame([40, 'Se corto la conexion'], [(int)$failed->status, $failed->error]);
        $this->assertNotNull($failed->finished_at);
        $stale = DB::table('system_rule_runs')->where('rule_model_id', self::rankingRuleId)->first();
        $this->assertSame([40, 'Se interrumpio antes de terminar'], [(int)$stale->status, $stale->error]);
        // reintenta en sus minutos de reintento (15 por defecto), no cada minuto
        $retryAt = CarbonImmutable::parse(DB::table('system_rules')->where('rule_model_id', 999)->value('next_run_at'));
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $retryAt->timestamp, 60);
    }

    public function test_retry_never_goes_past_the_next_regular_run_and_zero_means_no_retry(): void
    {
        config(['system_rules.rules' => [FailingSystemRule::class]]);
        $this->artisan('rules:run')->assertSuccessful();
        $failingRule = fn() => DB::table('system_rules')->where('rule_model_id', 999);
        $adminToken = $this->login(static::adminEmail);
        $syncFailing = fn(string $frequency, int $retryMinutes) => $this->actingWithToken($adminToken)->putJson('/api/v1/config/rules',
            ['rules' => [999 => ['is_enabled' => true, 'frequency' => $frequency, 'run_at' => '00:00', 'retry_minutes' => $retryMinutes]]])->assertOk();

        // cada hora con 120 minutos de reintento: la proxima vuelta normal llega antes, se queda con esa
        $syncFailing('hourly', 120);
        $failingRule()->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('rules:run')->doesntExpectOutputToContain('reintenta')->assertFailed();
        $this->assertTrue(CarbonImmutable::parse($failingRule()->value('next_run_at'))->lessThanOrEqualTo(now()->addHour()));

        // 0 minutos: no reintenta, espera su proximo horario
        $syncFailing('daily', 0);
        $failingRule()->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('rules:run')->doesntExpectOutputToContain('reintenta')->assertFailed();
        $this->assertSame('00:00', CarbonImmutable::parse($failingRule()->value('next_run_at'))->setTimezone('America/Asuncion')->format('H:i'));

        // ejecutada a mano tampoco agenda reintentos
        $syncFailing('daily', 15);
        $nextRunAt = $failingRule()->value('next_run_at');
        $this->artisan('rules:run', ['rule' => 'failing'])->doesntExpectOutputToContain('reintenta')->assertFailed();
        $this->assertSame($nextRunAt, $failingRule()->value('next_run_at'));
    }

    public function test_admin_runs_a_rule_now_and_a_running_one_is_skipped(): void
    {
        $adminToken = $this->login(static::adminEmail);
        $this->actingWithToken($adminToken)->postJson($this->ruleUrl().'/run')->assertOk()
            ->assertJsonPath('data.lastRun.status', 'completed')
            ->assertJsonPath('data.lastRun.isManual', true)
            ->assertJsonPath('data.lastRun.summary', ['gyms' => 0, 'closedSeasons' => 0, 'startNotices' => 0]);

        DB::table('system_rule_runs')->insert(['rule_model_id' => self::rankingRuleId, 'status' => 20, 'is_manual' => false,
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingWithToken($adminToken)->postJson($this->ruleUrl().'/run')->assertOk()
            ->assertJsonPath('data.lastRun.status', 'skipped')
            ->assertJsonPath('data.lastRun.error', 'Ya habia una ejecucion en curso');
    }

    public function test_season_started_today_by_the_trainer_is_announced_right_away(): void
    {
        [$coachToken, $coachId] = $this->approvedTrainer('coach@example.com');
        $gymId = $this->createGym('Gym Hoy', $coachId);
        $anaId = $this->member('ana@example.com', [$gymId], true);

        $this->actingWithToken($coachToken)->postJson('/api/v1/entities/gym/'.$gymId.'/seasons', ['starts_on' => $this->dayAgo(0), 'ends_on' => $this->dayAgo(-6)])
            ->assertCreated();

        $notice = DB::table('user_notifications')->where('user_id', $anaId)->where('type', 80)->first();
        $this->assertSame(['gymName' => 'Gym Hoy', 'season' => 1, 'endsOn' => $this->dayAgo(-6)], json_decode($notice->data, true));
        $this->assertNotNull(DB::table('gym_seasons')->where('gym_id', $gymId)->value('started_notified_at'));
        $inbox = $this->actingWithToken($this->login('ana@example.com'))->getJson('/api/v1/me/notifications')->json('data.items');
        $this->assertSame('season-started', $inbox[0]['type']);
    }

    private function rulesPayload(bool $isEnabled, string $frequency, string $runAt, int $retryMinutes = 15): array
    {
        return ['rules' => [self::rankingRuleId => ['is_enabled' => $isEnabled, 'frequency' => $frequency, 'run_at' => $runAt, 'retry_minutes' => $retryMinutes]]];
    }

    private function rankingRule()
    {
        return DB::table('system_rules')->where('rule_model_id', self::rankingRuleId);
    }

    private function ruleUrl(): string
    {
        return '/api/v1/config/rules/'.self::rankingRuleId;
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
            'name' => $name, 'slug' => str($name)->slug(), 'owner_id' => $ownerId, 'status' => 100, 'is_ranking_public' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(string $email, array $gymIdList, bool $isPublic): int
    {
        $userId = $this->register($email, 'trainee-role')->assertCreated()->json('data.user.id');
        foreach ($gymIdList as $gymId) {
            DB::table('gym_users')->insert(['gym_id' => $gymId, 'user_id' => $userId, 'is_public' => $isPublic, 'created_at' => now(), 'updated_at' => now()]);
        }
        return $userId;
    }

    private function insertSeason(int $gymId, int $number, string $startsOn, string $endsOn): void
    {
        DB::table('gym_seasons')->insert([
            'gym_id' => $gymId, 'number' => $number, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
