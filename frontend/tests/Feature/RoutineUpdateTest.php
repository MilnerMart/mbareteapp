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
}
