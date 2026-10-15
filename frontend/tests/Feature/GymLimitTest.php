<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GymLimitTest extends TestCase
{
    private array $managedGyms = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/entities/gym')
            ? $this->managedGyms
            : []]));
    }

    private function trainerSession(array $permits = ['create-gym-entity']): array
    {
        return ['auth_user' => ['id' => 7, 'permits' => $permits], 'auth_token' => 'token'];
    }

    public function test_trainer_without_gym_can_create_one(): void
    {
        $this->withSession($this->trainerSession())->get('/gym')->assertOk()->assertSee('Nuevo gimnasio');
        $this->withSession($this->trainerSession())->get('/gym/create')->assertOk();
    }

    public function test_trainer_can_add_a_second_gym_for_another_shift_but_not_a_third(): void
    {
        $this->managedGyms = [['id' => 3, 'name' => 'Coach Gym Mañana', 'slug' => 'coach-gym-am', 'ownerId' => 7, 'alumnsCount' => 20]];

        $this->withSession($this->trainerSession())->get('/gym')->assertOk()->assertSee('Nuevo gimnasio');
        $this->withSession($this->trainerSession())->get('/gym/create')->assertOk();

        $this->managedGyms[] = ['id' => 4, 'name' => 'Coach Gym Tarde', 'slug' => 'coach-gym-pm', 'ownerId' => 7, 'alumnsCount' => 12];
        $this->withSession($this->trainerSession())->get('/gym')->assertOk()->assertDontSee('Nuevo gimnasio');
        $this->withSession($this->trainerSession())->get('/gym/create')
            ->assertRedirect(route('gym.index'))
            ->assertSessionHasErrors(['name' => 'Cada entrenador puede tener hasta 2 gimnasios.']);
    }

    public function test_admin_keeps_creating_gyms(): void
    {
        $this->managedGyms = [['id' => 1, 'name' => 'Mbarete', 'slug' => 'mbarete', 'ownerId' => 7, 'alumnsCount' => 3]];

        $this->withSession($this->trainerSession(['the-one-who-sees-all']))->get('/gym')->assertOk()->assertSee('Nuevo gimnasio');
    }
}
