<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrainerBaseGymTest extends TestCase
{
    private const trainerSession = ['auth_user' => ['id' => 9, 'permits' => ['create-gym-entity', 'Assing-routines', 'belongs-to-gym', 'create-catalog-entity']], 'auth_token' => 'token'];

    private const baseGym = ['id' => 1, 'name' => 'Chavuku Gym', 'isBase' => true, 'isPublic' => false, 'isRankingPublic' => true, 'publicStudents' => [],
        'refs' => ['owner' => ['id' => 99, 'name' => 'Admin', 'last_name' => '']]];

    private array $requestList = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return match (true) {
                str_ends_with($path, '/entities/gym/1/ranking') => Http::response(['success' => true, 'data' => [
                    'gym' => ['id' => 1, 'name' => 'Chavuku Gym', 'isRankingPublic' => true], 'canManage' => false,
                    'current' => null, 'recap' => null, 'openRecap' => false, 'closedSeasonNumbers' => [],
                ]]),
                str_ends_with($path, '/me/gyms') => Http::response(['success' => true, 'data' => [self::baseGym]]),
                str_ends_with($path, '/entities/gym') => Http::response(['success' => true, 'data' => [
                    ['id' => 3, 'name' => 'Coach Gym', 'slug' => 'coach-gym', 'ownerId' => 9, 'alumnsCount' => 4],
                ]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_trainer_sees_the_base_gym_switch_and_can_open_it(): void
    {
        $this->withSession(self::trainerSession)->get('/gym')->assertOk()
            ->assertSee('Ver gimnasio Chavuku Gym')
            ->assertSee('Coach Gym');

        $this->withSession(self::trainerSession)->get('/gym?base_gym=1')->assertOk()
            ->assertSeeInOrder(['Coach Gym', 'Chavuku Gym']);
    }

    public function test_trainer_competes_in_the_base_gym_like_any_student(): void
    {
        $this->withSession(self::trainerSession)->get('/gym?member=1')->assertOk()
            ->assertSee('Mostrarme en la lista de alumnos')
            ->assertSee('Ranking del gimnasio')
            ->assertSee('El entrenador todavia no inicio una nueva temporada.');
        $this->assertContains(['GET', '/api/v1/entities/gym/1/ranking', []], $this->requestList);
    }
}
