<?php

namespace Tests\Feature;

use App\Services\ApiClientService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GymJoinTest extends TestCase
{
    private const authSession = ['auth_user' => ['id' => 1, 'permits' => ['belongs-to-gym']], 'auth_token' => 'token'];

    private const baseGym = ['id' => 1, 'name' => 'Mbarete', 'isBase' => true, 'isPublic' => false, 'publicStudents' => []];

    private const coachGym = ['id' => 2, 'name' => 'Coach Gym', 'isBase' => false, 'isPublic' => true, 'publicStudents' => []];

    private array $memberGyms = [self::baseGym];

    private array $myTickets = [];

    private array $joinResponse = [];

    private array $requestList = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return match (true) {
                str_ends_with($path, '/me/gyms/join') => Http::response($this->joinResponse, ($this->joinResponse['success'] ?? false) ? 201 : 400),
                str_ends_with($path, '/me/gyms') => Http::response(['success' => true, 'data' => $this->memberGyms]),
                str_ends_with($path, '/me/tickets') => Http::response(['success' => true, 'data' => $this->myTickets]),
                str_ends_with($path, '/user/5') => Http::response(['success' => true, 'data' => [
                    'favorites' => ['muscle' => ['image_url' => 'images/chavukuBiceps.png']],
                ]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_student_only_in_base_gym_sees_the_join_option(): void
    {
        $this->withSession(self::authSession)->get('/my-gym')->assertOk()
            ->assertSee('Unirme a un gimnasio')
            ->assertSee(route('gym.member.join'), false);
    }

    public function test_join_option_is_hidden_with_another_gym(): void
    {
        $this->memberGyms = [self::baseGym, self::coachGym];

        $this->withSession(self::authSession)->get('/my-gym')->assertOk()->assertDontSee('Unirme a un gimnasio');
    }

    public function test_pending_request_is_shown_instead_of_the_join_option(): void
    {
        $this->myTickets = [
            ['id' => 9, 'number' => 'TK-000009', 'type' => 'gym-join', 'state' => 'pending', 'refs' => ['gym' => ['id' => 2, 'name' => 'Coach Gym']]],
        ];

        $this->withSession(self::authSession)->get('/my-gym')->assertOk()
            ->assertDontSee('Unirme a un gimnasio')
            ->assertSee('Tu solicitud TK-000009 para unirte a <strong>Coach Gym</strong>', false);
    }

    public function test_join_request_sends_the_code_to_the_backend(): void
    {
        $this->joinResponse = ['success' => true, 'data' => [
            'id' => 9, 'number' => 'TK-000009', 'type' => 'gym-join', 'state' => 'pending', 'refs' => ['gym' => ['id' => 2, 'name' => 'Coach Gym']],
        ]];

        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->from('/my-gym')->post('/my-gym/join', ['gym_code' => 'coach-gym'])
            ->assertRedirect('/my-gym')
            ->assertSessionHas('gym_saved', 'Enviamos tu solicitud TK-000009 para unirte a Coach Gym. Te avisaremos cuando el entrenador la acepte.');
        $this->assertContains(['POST', '/api/v1/me/gyms/join', ['gym_code' => 'coach-gym']], $this->requestList);
    }

    public function test_backend_error_reopens_the_join_form_with_the_message(): void
    {
        $this->joinResponse = ['success' => false, 'error' => ['message' => 'No existe un gimnasio con el codigo: nope']];

        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->from('/my-gym')->post('/my-gym/join', ['gym_code' => 'nope'])
            ->assertRedirect('/my-gym');
        $this->get('/my-gym')->assertOk()
            ->assertSee('collapse show', false)
            ->assertSee('No existe un gimnasio con el codigo: nope');
    }

    public function test_relative_front_images_become_absolute_so_they_load_from_any_page(): void
    {
        $userInfo = app(ApiClientService::class)->getUser(5);

        $this->assertSame('/images/chavukuBiceps.png', $userInfo['favorites']['muscle']['image_url']);
    }
}
