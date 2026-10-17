<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserDeactivationPageTest extends TestCase
{
    private const adminSession = ['auth_user' => ['id' => 1, 'permits' => ['the-one-who-sees-all', 'create-gym-entity']], 'auth_token' => 'token'];

    private const moderatorSession = ['auth_user' => ['id' => 3, 'permits' => ['see-user-detail', 'deactivate-user', 'create-gym-entity']], 'auth_token' => 'token'];

    private const viewerSession = ['auth_user' => ['id' => 4, 'permits' => ['see-user-detail', 'create-gym-entity']], 'auth_token' => 'token'];

    private const student = ['id' => 5, 'name' => 'Ana', 'last_name' => 'Paz', 'email' => 'ana@example.com', 'is_active' => true, 'routines' => []];

    private function fakeDetail(array $values): void
    {
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => $values + [
            'id' => 5, 'name' => 'Ana', 'last_name' => 'Paz', 'email' => 'ana@example.com', 'email_verified' => true,
            'roles' => ['Alumno'], 'gyms' => ['Chavuku Gym'], 'routines' => [], 'pending_profile_fields' => [],
            'favorites' => [], 'progress' => ['rank' => 'novice'],
        ]]));
    }

    public function test_admin_can_deactivate_from_the_detail_with_a_reason(): void
    {
        $this->fakeDetail(['can_deactivate' => true, 'deactivation' => null]);

        $this->withSession(self::adminSession)->get('/users/5/detail')->assertOk()
            ->assertSee('action="'.route('user.deactivate', 5).'"', false)
            ->assertSee('data-confirm-reason', false)
            ->assertSee('data-reason-word="confirmar"', false)
            ->assertSee('data-reason-min="5"', false)
            ->assertSee('Ana Paz no podra volver a iniciar sesion')
            ->assertDontSee('Dado de baja el');
    }

    public function test_deactivate_permit_opens_the_detail_and_deactivates_without_the_activity_log(): void
    {
        $this->fakeDetail(['can_deactivate' => true, 'deactivation' => null]);

        $this->withSession(self::moderatorSession)->get('/users/5/detail')->assertOk()
            ->assertSee('data-confirm-reason', false)
            ->assertDontSee(route('user.activity', 5), false);
        $this->withSession(self::moderatorSession)->get('/users/5/activity')->assertRedirect();
        $this->withSession(['auth_user' => ['id' => 2, 'permits' => ['create-gym-entity']], 'auth_token' => 'token'])
            ->get('/users/5/detail')->assertRedirect();

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::moderatorSession)->from('/gym/2')
            ->post('/users/5/deactivate', ['reason' => 'Cuenta duplicada'])
            ->assertRedirect('/gym/2')
            ->assertSessionHas('gym_saved', 'Ana Paz fue dado de baja.');
    }

    public function test_see_user_detail_alone_opens_the_detail_but_cannot_deactivate(): void
    {
        $this->fakeDetail(['can_deactivate' => false, 'deactivation' => null]);

        $this->withSession(self::viewerSession)->get('/users/5/detail')->assertOk()
            ->assertSee('Ana Paz')
            ->assertDontSee('data-confirm-reason', false);
        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::viewerSession)
            ->post('/users/5/deactivate', ['reason' => 'Cuenta duplicada'])->assertRedirect(route('muscle.index'));
        $this->withSession(['auth_user' => ['id' => 2, 'permits' => ['deactivate-user']], 'auth_token' => 'token'])
            ->get('/users/5/detail')->assertRedirect(route('muscle.index'));
        Http::assertNotSent(fn(Request $request) => $request->method() === 'POST');
    }

    public function test_admin_sees_the_activity_log_link_in_the_detail(): void
    {
        $this->fakeDetail(['can_deactivate' => true, 'deactivation' => null]);

        $this->withSession(self::adminSession)->get('/users/5/detail')->assertOk()
            ->assertSee(route('user.activity', 5), false);
    }

    public function test_detail_of_a_deactivated_user_shows_when_who_and_why(): void
    {
        $this->fakeDetail(['can_deactivate' => false, 'deactivation' => [
            'at' => '2026-10-15T10:00:00-03:00', 'byName' => 'Admin MuscleApp', 'reason' => 'Cuenta duplicada',
        ]]);

        $this->withSession(self::adminSession)->get('/users/5/detail')->assertOk()
            ->assertSee('Dado de baja el')
            ->assertSee('por Admin MuscleApp')
            ->assertSee('Motivo: Cuenta duplicada')
            ->assertDontSee('data-confirm-reason', false);
    }

    public function test_deactivation_sends_the_reason_and_only_the_admin_can_do_it(): void
    {
        $sentList = [];
        Http::fake(function (Request $request) use (&$sentList) {
            $sentList[] = [$request->method(), parse_url($request->url(), PHP_URL_PATH), $request->data()];
            return Http::response(['success' => true, 'data' => ['id' => 5, 'name' => 'Ana', 'last_name' => 'Paz', 'is_active' => false]]);
        });

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->withSession(['auth_user' => ['id' => 2, 'permits' => ['create-gym-entity']], 'auth_token' => 'token'])
            ->post('/users/5/deactivate', ['reason' => 'Cuenta duplicada'])->assertRedirect();
        $this->assertEmpty($sentList);

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::adminSession)->from('/gym/1')
            ->post('/users/5/deactivate', ['reason' => 'mal'])
            ->assertRedirect('/gym/1')
            ->assertSessionHasErrors(['reason' => 'El motivo de la baja tiene que tener al menos 5 caracteres.']);
        $this->assertEmpty($sentList);

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::adminSession)->from('/gym/1')
            ->post('/users/5/deactivate', ['reason' => '  Cuenta duplicada '])
            ->assertRedirect('/gym/1')
            ->assertSessionHas('gym_saved', 'Ana Paz fue dado de baja.');
        $this->assertSame([['POST', '/api/v1/user/5/deactivate', ['reason' => 'Cuenta duplicada']]], $sentList);
    }

    public function test_base_gym_students_cannot_be_removed_and_deactivated_ones_are_marked(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            return match (true) {
                str_ends_with($path, '/entities/gym/1/users') => Http::response(['success' => true, 'data' => [
                    self::student, ['id' => 6, 'name' => 'Beto', 'last_name' => 'Gil', 'email' => 'beto@example.com', 'is_active' => false, 'routines' => []],
                ]]),
                str_ends_with($path, '/entities/gym/1') => Http::response(['success' => true, 'data' => ['id' => 1, 'name' => 'Chavuku Gym', 'slug' => 'chavuku-gym', 'isBase' => true, 'alumnsCount' => 2]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });

        $this->withSession(self::adminSession)->get('/gym/1')->assertOk()
            ->assertSee('Ana Paz')
            ->assertSee('Dado de baja')
            ->assertDontSee(route('gym.users.remove', [1, 5]), false);
    }

    public function test_other_gyms_still_let_the_trainer_remove_students(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            return match (true) {
                str_ends_with($path, '/entities/gym/2/users') => Http::response(['success' => true, 'data' => [self::student]]),
                str_ends_with($path, '/entities/gym/2') => Http::response(['success' => true, 'data' => ['id' => 2, 'name' => 'Coach Gym', 'slug' => 'coach-gym', 'isBase' => false, 'alumnsCount' => 1]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });

        $this->withSession(['auth_user' => ['id' => 9, 'permits' => ['create-gym-entity']], 'auth_token' => 'token'])->get('/gym/2')->assertOk()
            ->assertSee(route('gym.users.remove', [2, 5]), false)
            ->assertDontSee('Dado de baja')
            ->assertDontSee('data-user-detail-url', false);

        // con el permiso de ver la ficha el entrenador tambien la abre desde la lista de sus alumnos
        $this->withSession(self::viewerSession)->get('/gym/2')->assertOk()
            ->assertSee('data-user-detail-url="'.route('user.detail', 5).'"', false);
    }
}
