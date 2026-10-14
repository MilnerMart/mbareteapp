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

    public function test_trainer_with_a_gym_cannot_create_another(): void
    {
        $this->managedGyms = [['id' => 3, 'name' => 'Coach Gym', 'slug' => 'coach-gym', 'ownerId' => 7, 'alumnsCount' => 0]];

        $this->withSession($this->trainerSession())->get('/gym')->assertOk()->assertDontSee('Nuevo gimnasio');
        $this->withSession($this->trainerSession())->get('/gym/create')
            ->assertRedirect(route('gym.index'))
            ->assertSessionHasErrors(['name' => 'Por ahora cada entrenador puede tener un solo gimnasio.']);
    }

    public function test_admin_keeps_creating_gyms(): void
    {
        $this->managedGyms = [['id' => 1, 'name' => 'Mbarete', 'slug' => 'mbarete', 'ownerId' => 7, 'alumnsCount' => 3]];

        $this->withSession($this->trainerSession(['the-one-who-sees-all']))->get('/gym')->assertOk()->assertSee('Nuevo gimnasio');
    }
}
