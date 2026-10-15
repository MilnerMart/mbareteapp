<?php

namespace Tests\Feature;

use App\Support\NotificationLabels;
use App\Support\SystemRuleLabels;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemRulesConfigTest extends TestCase
{
    private const configSession = ['auth_user' => ['id' => 1, 'permits' => ['allow-config']], 'auth_token' => 'token'];

    private array $requestList = [];

    private array $runResponse = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return match (true) {
                str_ends_with($path, '/config/rules/170/run') => Http::response($this->runResponse),
                str_ends_with($path, '/config/rules') => Http::response(['success' => true, 'data' => [$this->rule()]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_admin_sees_each_rule_with_its_schedule_and_last_runs(): void
    {
        $this->withSession(self::configSession)->get('/config')->assertOk()->assertSee('Reglas del sistema')->assertSee(route('config.rules'));

        $this->withSession(self::configSession)->get('/config/rules')->assertOk()
            ->assertSeeInOrder(['Ranking de temporadas', 'Cierra las temporadas que terminaron', 'Activa', 'Frecuencia', 'Todos los dias', 'Hora'])
            ->assertSee('name="rules[170][run_at]"', false)
            ->assertSee('value="00:05"', false)
            ->assertSeeInOrder(['Reintentar si falla (minutos)', 'name="rules[170][retry_minutes]"', 'value="15"'], false)
            ->assertSeeInOrder(['Todos los dias a las 00:05 (hora de Paraguay)', 'Si falla, reintenta a los 15 minutos (sin pasarse de su proximo horario).',
                'Proxima: 16/10/2026 00:05'])
            ->assertSeeInOrder(['Últimas ejecuciones', 'Ejecutar ahora',
                'Completada', '15/10/2026 00:05 · Automatica · 2 s', '3 gimnasios revisados · 1 temporada cerrada · 0 avisos de inicio',
                'Fallida', '14/10/2026 00:05 · Automatica', 'Se corto la conexion',
                'Omitida', 'Manual', 'Ya habia una ejecucion en curso'])
            ->assertSee('form="runRule170"', false)
            ->assertSee(route('config.rules.run', 170), false);
    }

    public function test_all_rules_are_saved_in_one_request(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::configSession)->from('/config/rules')
            ->put('/config/rules', ['rules' => [170 => ['is_enabled' => '0', 'frequency' => 'hourly', 'run_at' => '07:30', 'retry_minutes' => '0']]])
            ->assertRedirect('/config/rules')->assertSessionHas('config_saved');

        $this->assertContains(['PUT', '/api/v1/config/rules', ['rules' => [170 => ['is_enabled' => '0', 'frequency' => 'hourly', 'run_at' => '07:30', 'retry_minutes' => '0']]]], $this->requestList);

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::configSession)->from('/config/rules')
            ->put('/config/rules', ['rules' => [170 => ['is_enabled' => '1', 'frequency' => 'weekly', 'run_at' => '7', 'retry_minutes' => '2000']]])
            ->assertRedirect('/config/rules')->assertSessionHasErrors(['rules.170.frequency', 'rules.170.run_at', 'rules.170.retry_minutes']);
    }

    public function test_run_now_says_what_the_rule_did_or_why_it_failed(): void
    {
        $this->runResponse = ['success' => true, 'data' => $this->rule() + ['lastRun' => [
            'status' => 'completed', 'summary' => ['gyms' => 1, 'closedSeasons' => 0, 'startNotices' => 4], 'error' => null,
        ]]];
        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::configSession)->from('/config/rules')
            ->post('/config/rules/170/run')
            ->assertRedirect('/config/rules')
            ->assertSessionHas('config_saved', 'Regla ejecutada: 1 gimnasio revisado · 0 temporadas cerradas · 4 avisos de inicio.');

        $this->runResponse['data']['lastRun'] = ['status' => 'failed', 'summary' => [], 'error' => 'Se corto la conexion'];
        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::configSession)->from('/config/rules')
            ->post('/config/rules/170/run')
            ->assertSessionHasErrors(['rules' => 'La regla fallo: Se corto la conexion']);
    }

    public function test_only_who_configures_the_app_reaches_the_rules(): void
    {
        $this->withSession(['auth_user' => ['id' => 2, 'permits' => ['belongs-to-gym']], 'auth_token' => 'token'])->get('/config/rules')->assertRedirect();
        $this->withSession(['auth_user' => ['id' => 3, 'permits' => ['the-one-who-sees-all']], 'auth_token' => 'token'])->get('/config/rules')->assertOk();
    }

    public function test_reminders_rule_reads_its_own_summary(): void
    {
        $this->assertSame('1 anotacion avisada', SystemRuleLabels::summaryText('reminders', ['notes' => 1]));
        $this->assertSame('0 anotaciones avisadas', SystemRuleLabels::summaryText('reminders', ['notes' => 0]));
    }

    public function test_season_started_notice_reads_well_and_opens_the_gym(): void
    {
        $this->withSession(self::configSession);
        $notification = ['id' => 8, 'type' => 'season-started', 'refId' => 2, 'isRead' => false, 'createdAt' => '2026-10-15T00:05:00-03:00',
            'data' => ['gymName' => 'Coach Gym', 'season' => 3, 'endsOn' => '2026-11-13']];

        $item = NotificationLabels::buildItem($notification);

        $this->assertSame(['fa-solid fa-flag-checkered', 'Empezo la temporada 3 de Coach Gym', 'Termina el 13/11/2026. ¡Cada entrenamiento suma fuerza!'],
            [$item['icon'], $item['text'], $item['detail']]);
        $this->assertSame(route('gym.member', ['member' => 2]), NotificationLabels::targetUrl($notification));
    }

    private function rule(): array
    {
        return [
            'ruleModelId' => 170, 'slug' => 'ranking', 'isEnabled' => true, 'frequency' => 'daily', 'runAt' => '00:05', 'retryMinutes' => 15,
            'timezone' => 'America/Asuncion', 'nextRunAt' => '2026-10-16T00:05:00-03:00',
            'runs' => [
                ['id' => 3, 'status' => 'completed', 'isManual' => false, 'createdAt' => '2026-10-15T00:05:00-03:00',
                    'startedAt' => '2026-10-15T00:05:00-03:00', 'finishedAt' => '2026-10-15T00:05:02-03:00',
                    'summary' => ['gyms' => 3, 'closedSeasons' => 1, 'startNotices' => 0], 'error' => null],
                ['id' => 2, 'status' => 'failed', 'isManual' => false, 'createdAt' => '2026-10-14T00:05:00-03:00',
                    'startedAt' => '2026-10-14T00:05:00-03:00', 'finishedAt' => '2026-10-14T00:05:01-03:00', 'summary' => [], 'error' => 'Se corto la conexion'],
                ['id' => 1, 'status' => 'skipped', 'isManual' => true, 'createdAt' => '2026-10-13T09:00:00-03:00',
                    'startedAt' => null, 'finishedAt' => '2026-10-13T09:00:00-03:00', 'summary' => [], 'error' => 'Ya habia una ejecucion en curso'],
            ],
        ];
    }
}
