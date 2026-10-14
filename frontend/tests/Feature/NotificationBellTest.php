<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    private const inbox = [
        'unreadCount' => 1,
        'pendingProfileFields' => ['gender', 'weight'],
        'items' => [
            ['id' => 7, 'type' => 'ticket-received', 'refId' => 3, 'isRead' => false, 'createdAt' => '2026-10-13T10:00:00-03:00',
                'data' => ['ticketNumber' => 'TK-000003', 'ticketType' => 'gym-join', 'requesterName' => 'Ana Paz', 'gymName' => 'Coach Gym']],
            ['id' => 6, 'type' => 'routine-assigned', 'refId' => 20, 'isRead' => true, 'createdAt' => '2026-10-12T10:00:00-03:00',
                'data' => ['routineName' => 'Fuerza', 'assignerName' => 'Coach']],
        ],
    ];

    private function authSession(array $permits = []): array
    {
        return ['auth_user' => ['id' => 1, 'permits' => $permits], 'auth_token' => 'token'];
    }

    public function test_bell_shows_notifications_and_pending_profile_data(): void
    {
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => str_contains($request->url(), '/me/notifications') ? self::inbox : []]));

        $this->withSession($this->authSession())->get('/routine/create')
            ->assertOk()
            ->assertSee('nav-bell-badge', false)
            ->assertSeeInOrder(['Completa los datos de tu perfil', 'Falta: genero, peso', 'Tienes un nuevo ticket de ingreso a gimnasio', 'Ana Paz · Coach Gym · TK-000003', 'Te asignaron la rutina Fuerza'])
            ->assertSee(route('user.profile', ['id' => 1, 'edit' => 1]), false)
            ->assertSee('Marcar todo como leido');
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_its_target(): void
    {
        $requestList = [];
        Http::fake(function (Request $request) use (&$requestList) {
            $requestList[] = [$request->method(), $request->data()];
            return Http::response(['success' => true, 'data' => self::inbox]);
        });

        $this->withSession($this->authSession())->get('/notifications/6')->assertRedirect(route('routine.show', 20));
        $this->withSession($this->authSession())->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->from('/routine')->post('/notifications/read')->assertRedirect('/routine');

        $this->assertSame([['PUT', ['ids' => [6]]], ['PUT', []]], $requestList);
    }

    public function test_bell_is_kept_in_session_and_refreshed_only_when_marking_read(): void
    {
        $requestList = [];
        $readInbox = ['unreadCount' => 0, 'pendingProfileFields' => [], 'items' => [array_merge(self::inbox['items'][0], ['isRead' => true])]];
        Http::fake(function (Request $request) use (&$requestList, $readInbox) {
            $requestList[] = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
            $data = match (true) {
                $request->method() === 'PUT' => $readInbox,
                str_contains($request->url(), '/me/notifications') => array_merge(self::inbox, ['pendingProfileFields' => []]),
                default => [],
            };
            return Http::response(['success' => true, 'data' => $data]);
        });

        $this->withSession($this->authSession())->get('/routine/create')->assertOk()->assertSee('has-unread', false);
        $this->get('/routine/create')->assertOk()->assertSee('has-unread', false);
        $this->assertSame(1, count(array_filter($requestList, fn(string $call) => str_ends_with($call, '/me/notifications'))));

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->from('/routine/create')->post('/notifications/read')->assertRedirect('/routine/create');
        $this->get('/routine/create')->assertOk()->assertDontSee('has-unread')->assertDontSee('nav-bell-badge');
        $this->assertSame(1, count(array_filter($requestList, fn(string $call) => str_ends_with($call, '/me/notifications'))));
    }

    public function test_only_admin_opens_the_read_only_user_detail(): void
    {
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => [
            'id' => 5, 'name' => 'Ana', 'last_name' => 'Paz', 'email' => 'ana@example.com', 'email_verified' => true,
            'age' => 25, 'height' => 165, 'weight' => 60, 'goal' => 'strength', 'gender' => null,
            'roles' => ['Alumno'], 'gyms' => ['Coach Gym'], 'routines' => [['id' => 20, 'name' => 'Fuerza']],
            'pending_profile_fields' => ['gender'], 'created_at' => '2026-10-01T10:00:00-03:00',
            'favorites' => ['routine' => ['id' => 20, 'name' => 'Fuerza', 'exerciseCount' => 3], 'muscle' => null, 'exercise' => null],
            'progress' => ['rank' => 'novice', 'points' => 120, 'trainingDays' => 4],
        ]]));

        $this->withSession($this->authSession())->get('/users/5/detail')->assertRedirect();

        $this->withSession($this->authSession(['the-one-who-sees-all']))->get('/users/5/detail')
            ->assertOk()
            ->assertSee('Ana Paz')
            ->assertSee('ana@example.com')
            ->assertSee('Datos sin completar: genero')
            ->assertSee('Ganar fuerza')
            ->assertSee('Dias entrenados')
            ->assertSee(route('routine.show', 20), false)
            ->assertSee(route('user.activity', 5), false)
            ->assertDontSee('<form', false);
    }

    public function test_activity_that_fails_to_load_goes_back_with_a_message(): void
    {
        Http::fake(fn(Request $request) => str_contains($request->url(), '/logs')
            ? Http::response(['success' => false], 500)
            : Http::response(['success' => true, 'data' => []]));

        $this->withSession($this->authSession(['the-one-who-sees-all']))->from('/gym/1')->get('/users/5/activity')
            ->assertRedirect('/gym/1')
            ->assertSessionHas('auth_error', 'No pudimos cargar el registro de actividad. Intenta de nuevo.');
    }

    public function test_admin_reads_user_activity_grouped_by_day_with_filters_and_pages(): void
    {
        $requestList = [];
        Http::fake(function (Request $request) use (&$requestList) {
            $requestList[] = $request->url();
            $data = str_contains($request->url(), '/logs') ? [
                'user' => ['id' => 5, 'name' => 'Ana', 'last_name' => 'Paz', 'profile_image_url' => null],
                'page' => 2,
                'hasMore' => true,
                'items' => [
                    ['id' => 3, 'action' => 'exercise-added', 'model' => 'routine', 'entityId' => 20, 'createdAt' => '2026-10-13T18:30:00-03:00',
                        'data' => ['name' => 'Fuerza', 'exerciseName' => 'Sentadilla']],
                    ['id' => 2, 'action' => 'assigned', 'model' => 'routine', 'entityId' => 20, 'createdAt' => '2026-10-13T09:05:00-03:00',
                        'data' => ['name' => 'Fuerza', 'targetUserName' => 'Leo Gomez']],
                    ['id' => 1, 'action' => 'created', 'model' => 'muscle', 'entityId' => 8, 'createdAt' => '2026-10-12T10:00:00-03:00',
                        'data' => ['name' => 'Pectoral', 'pendingReview' => true]],
                    ['id' => 0, 'action' => 'rank-up', 'model' => 'user', 'entityId' => 5, 'createdAt' => '2026-10-12T09:00:00-03:00',
                        'data' => ['name' => 'Ana Paz', 'rank' => 'intermediate', 'gender' => 'female']],
                    ['id' => -1, 'action' => 'updated', 'model' => 'user', 'entityId' => 5, 'createdAt' => '2026-10-12T08:00:00-03:00',
                        'data' => ['name' => 'Ana Paz', 'fields' => ['weight', 'gender']]],
                    ['id' => -2, 'action' => 'rejected', 'model' => 'ticket', 'entityId' => 3, 'createdAt' => '2026-10-12T07:00:00-03:00',
                        'data' => ['name' => 'TK-000003', 'ticketType' => 'gym-join', 'requesterName' => 'Leo Gomez', 'note' => 'Sin cupo']],
                ],
            ] : [];
            return Http::response(['success' => true, 'data' => $data]);
        });

        $this->withSession($this->authSession())->get('/users/5/activity')->assertRedirect();

        $this->withSession($this->authSession(['the-one-who-sees-all']))->get('/users/5/activity?model=routine&page=2')
            ->assertOk()
            ->assertSee('Ana Paz')
            ->assertSeeInOrder(['13/10/2026', 'Agrego el ejercicio «Sentadilla» a la rutina «Fuerza»', '18:30',
                'Asigno la rutina «Fuerza» a Leo Gomez', '12/10/2026', 'Creo el musculo «Pectoral»', 'Quedo pendiente de aprobacion',
                'Subio al rango Intermedia', 'Actualizo su perfil: peso, genero',
                'Rechazo la solicitud TK-000003 (Ingreso a gimnasio) de Leo Gomez', 'Sin cupo'])
            ->assertSee(route('user.activity', ['id' => 5, 'model' => 'routine', 'page' => 1]))
            ->assertSee(route('user.activity', ['id' => 5, 'model' => 'routine', 'page' => 3]));
        $this->assertTrue(collect($requestList)->contains(fn(string $url) => str_contains($url, '/user/5/logs?page=2&model=routine')));
    }
}
