<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProgressConfigTest extends TestCase
{
    private const adminSession = ['auth_user' => ['id' => 1, 'permits' => ['the-one-who-sees-all']], 'auth_token' => 'token'];

    private const settings = [
        'scoring' => ['pointsPerDay' => 10, 'pointsPerStreakDay' => 5, 'pointsPerMinute' => 1, 'kgPerPoint' => 100, 'maxPointsPerDay' => 60],
        'ranks' => [
            'novice' => ['minPoints' => 0],
            'beginner' => ['minPoints' => 150],
            'steady' => ['minPoints' => 400],
            'intermediate' => ['minPoints' => 900],
            'advanced' => ['minPoints' => 1800],
            'elite' => ['minPoints' => 3500],
            'beast' => ['minPoints' => 6000],
        ],
    ];

    private array $requestList = [];

    private array $profile = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return Http::response(['success' => true, 'data' => match (true) {
                str_ends_with($path, '/config/progress') => self::settings,
                str_ends_with($path, '/user/1') => $this->profile,
                default => [],
            }]);
        });
    }

    public function test_admin_sees_the_ranks_section_with_current_values(): void
    {
        $this->withSession(self::adminSession)->get('/config')->assertOk()->assertSee('Rangos y puntos');
        $this->withSession(self::adminSession)->get('/config/progress')->assertOk()
            ->assertSeeInOrder(['Puntos por dia entrenado', 'Tope de puntos por dia', 'Novato', 'Rango de entrada', 'Principiante', 'Constante', 'Bestia'])
            ->assertSee('name="ranks[beginner][minPoints]" class="form-control" value="150"', false)
            ->assertSee('name="scoring[maxPointsPerDay]" class="form-control"', false)
            ->assertDontSee('minDays', false);
    }

    public function test_ranks_are_saved_in_a_single_request(): void
    {
        $payload = self::settings;
        $payload['scoring']['maxPointsPerDay'] = 80;

        $this->withSession(self::adminSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->put('/config/progress', $payload)
            ->assertRedirect(route('config.progress'))
            ->assertSessionHas('config_saved');
        $putList = array_values(array_filter($this->requestList, fn(array $call) => $call[0] === 'PUT'));
        $this->assertCount(1, $putList);
        $this->assertEquals('80', $putList[0][2]['scoring']['maxPointsPerDay']);
    }

    public function test_users_without_config_permit_cannot_open_it(): void
    {
        $this->withSession(['auth_user' => ['id' => 2, 'permits' => []], 'auth_token' => 'token'])->get('/config/progress')->assertRedirect();
    }

    public function test_profile_says_how_many_points_are_missing(): void
    {
        $this->profile = ['id' => 1, 'name' => 'Ana', 'last_name' => 'Paz', 'favorites' => [], 'achievements' => [],
            'progress' => ['rank' => 'novice', 'nextRank' => 'beginner', 'points' => 100, 'nextRankPoints' => 150]];
        $this->withSession(self::adminSession)->get('/profile/1')->assertOk()
            ->assertSee('Faltan 50 puntos para Principiante')
            ->assertSee('Cada dia suma hasta un tope de puntos');
    }
}
