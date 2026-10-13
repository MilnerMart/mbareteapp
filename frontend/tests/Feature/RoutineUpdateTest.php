<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutineUpdateTest extends TestCase
{
    public function test_exercises_keep_the_order_of_the_form_even_when_the_first_one_only_has_minutes(): void
    {
        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            if ($request->method() === 'PUT') {
                $payload = $request->data();
            }
            return Http::response(['success' => true, 'data' => ['id' => 20]]);
        });

        $body = '_method=PUT&sync_exercises=1&name=Espalda+y+triceps&description=Rutina&frequency=2&rest_minutes=2'
            .'&exercises%5B37%5D%5Bduration_minutes%5D=10&exercises%5B37%5D%5Bnotes%5D=Bici'
            .'&exercises%5B23%5D%5Bsets%5D=4&exercises%5B23%5D%5Breps%5D=12&exercises%5B23%5D%5Bnotes%5D=Controlado'
            .'&exercises%5B49%5D%5Bsets%5D=4&exercises%5B49%5D%5Breps%5D=12&exercises%5B49%5D%5Bnotes%5D='
            .'&exercises%5B47%5D%5Bsets%5D=4&exercises%5B47%5D%5Breps%5D=10&exercises%5B47%5D%5Bnotes%5D=';
        parse_str($body, $input);

        $this->withSession(['auth_user' => ['id' => 1], 'auth_token' => 'token'])
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post('/routine/20', $input)
            ->assertRedirect(route('routine.show', 20));

        $this->assertSame([37, 23, 49, 47], array_column($payload['exercises'], 'exercise_id'));
        $this->assertSame(['10', null, null], [$payload['exercises'][0]['duration_minutes'], $payload['exercises'][0]['sets'], $payload['exercises'][2]['notes']]);
    }

    public function test_done_exercises_of_the_day_are_saved_in_a_single_request(): void
    {
        $requestList = [];
        Http::fake(function (Request $request) use (&$requestList) {
            $requestList[] = [$request->method(), $request->url(), $request->data()];
            return Http::response(['success' => true, 'data' => []]);
        });
        $session = ['auth_user' => ['id' => 1], 'auth_token' => 'token'];

        $this->withSession($session)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->from(route('routine.show', 20))
            ->put('/routine/20/done', ['exercise_ids' => ['', '37', '49'], 'weights' => ['37' => '', '49' => '42.5', '50' => '80']])
            ->assertRedirect(route('routine.show', 20));
        $this->withSession($session)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->from(route('routine.show', 20))
            ->put('/routine/20/done', ['exercise_ids' => ['']])
            ->assertRedirect(route('routine.show', 20));

        $this->assertCount(2, $requestList);
        $this->assertSame(['PUT', ['exercises' => [['exercise_id' => 37, 'weight_kg' => null], ['exercise_id' => 49, 'weight_kg' => 42.5]]]],
            [$requestList[0][0], $requestList[0][2]]);
        $this->assertStringEndsWith('/routines/20/done', $requestList[0][1]);
        $this->assertSame(['exercises' => []], $requestList[1][2]);
    }

    public function test_profile_update_normalizes_names_and_only_sends_profile_fields(): void
    {
        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();
            return Http::response(['success' => true, 'data' => ['id' => 1, 'name' => 'María José', 'last_name' => 'Núñez']]);
        });

        $this->withSession(['auth_user' => ['id' => 1, 'name' => 'Vieja'], 'auth_token' => 'token'])
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->put('/profile/1', [
                'name' => '  María   José ', 'last_name' => 'Núñez', 'age' => 30, 'height' => 170, 'weight' => 65,
                'goal' => 'hypertrophy', 'gender' => '', 'level' => 'beginner', 'favorite_routine_id' => 5,
            ])
            ->assertRedirect(route('user.profile', 1))
            ->assertSessionHas('auth_user.name', 'María José');

        $this->assertSame(['name', 'last_name', 'age', 'height', 'weight', 'goal', 'gender'], array_keys($payload));
        $this->assertSame(['María José', 'hypertrophy', null], [$payload['name'], $payload['goal'], $payload['gender']]);

        $this->withSession(['auth_user' => ['id' => 1], 'auth_token' => 'token'])
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->from(route('user.profile', 1))
            ->put('/profile/1', ['name' => 'juan@mail.com', 'last_name' => 'Núñez', 'age' => 30, 'height' => 170, 'weight' => 65])
            ->assertSessionHasErrors('name');
    }
}
