<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileRestDaysTest extends TestCase
{
    private const authSession = ['auth_user' => ['id' => 1, 'permits' => []], 'auth_token' => 'token'];

    private const profileData = ['name' => 'Ana', 'last_name' => 'Paz', 'age' => 30, 'height' => 165, 'weight' => 60];

    private function fakeProfile(array $restDayList): void
    {
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/me/tickets') ? [] : [
            'id' => 1, 'name' => 'Ana', 'last_name' => 'Paz', 'gender' => null, 'rest_days' => $restDayList,
            'favorites' => [], 'achievements' => [], 'progress' => ['rank' => 'novice'],
        ]]));
    }

    public function test_profile_shows_the_rest_days_selected(): void
    {
        $this->fakeProfile([6, 7]);

        $this->withSession(self::authSession)->get('/profile/1')->assertOk()
            ->assertSee('<select id="rest_days" name="rest_days[]" class="form-select" multiple data-searchable', false)
            ->assertSee('<option value="6" selected>Sabado</option>', false)
            ->assertSee('<option value="7" selected>Domingo</option>', false)
            ->assertSee('<option value="1" >Lunes</option>', false)
            ->assertSee('Dias de descanso (no cortan la racha): sabado y domingo.');
    }

    public function test_without_rest_days_the_streak_asks_for_every_day(): void
    {
        $this->fakeProfile([]);

        $this->withSession(self::authSession)->get('/profile/1')->assertOk()
            ->assertSee('Sin dias de descanso: para mantener la racha hay que entrenar todos los dias.');
    }

    public function test_saving_sends_the_rest_days_and_an_empty_list_when_none_is_chosen(): void
    {
        $this->fakeProfile([]);

        $this->withSession(self::authSession)->put('/profile/1', self::profileData + ['rest_days' => ['3', '6']])
            ->assertRedirect('/profile/1');
        Http::assertSent(fn(Request $request) => $request->method() === 'PUT' && $request['rest_days'] === [3, 6]);

        $this->withSession(self::authSession)->put('/profile/1', self::profileData)->assertRedirect('/profile/1');
        Http::assertSent(fn(Request $request) => $request->method() === 'PUT' && $request['rest_days'] === []);
    }

    public function test_at_least_one_training_day_is_required(): void
    {
        $this->fakeProfile([]);

        $this->withSession(self::authSession)->from('/profile/1')
            ->put('/profile/1', self::profileData + ['rest_days' => ['1', '2', '3', '4', '5', '6', '7']])
            ->assertRedirect('/profile/1')
            ->assertSessionHasErrors(['rest_days' => 'Deja al menos un dia de entrenamiento en la semana.']);
        Http::assertNotSent(fn(Request $request) => $request->method() === 'PUT');
    }
}
