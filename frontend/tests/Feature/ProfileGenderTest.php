<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileGenderTest extends TestCase
{
    private const authSession = ['auth_user' => ['id' => 1, 'permits' => []], 'auth_token' => 'token'];

    private function fakeProfile(?string $gender): void
    {
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/user/1') ? [
            'id' => 1, 'name' => 'Ana', 'last_name' => 'Paz', 'gender' => $gender,
            'favorites' => [], 'achievements' => [], 'progress' => ['rank' => 'novice'],
        ] : []]));
    }

    public function test_gender_can_be_chosen_while_undefined(): void
    {
        $this->fakeProfile(null);

        $this->withSession(self::authSession)->get('/profile/1')->assertOk()
            ->assertDontSee('<select id="gender" name="gender" class="form-select" disabled>', false)
            ->assertSee('Una vez guardado no se puede cambiar.')
            ->assertSee('El genero no se puede cambiar despues.');
    }

    public function test_gender_is_locked_once_defined(): void
    {
        $this->fakeProfile('female');

        $this->withSession(self::authSession)->get('/profile/1')->assertOk()
            ->assertSee('<select id="gender" name="gender" class="form-select" disabled>', false)
            ->assertSee('<option value="female" selected>Femenino</option>', false)
            ->assertSee('Ya lo definiste y no se puede cambiar.')
            ->assertSee('Se actualizan tus datos y tu objetivo.');
    }
}
