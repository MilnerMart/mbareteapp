<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Leoncio',
            'last_name' => 'Gym',
            'email' => 'leoncio@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'age' => 30,
            'height' => 178,
            'weight' => 82,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sucess', true)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'last_name', 'email', 'age', 'height', 'weight'],
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_user_can_login_read_profile_and_logout_with_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        $token = $login->json('data.token');

        $login->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sucess', true);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_register_validates_required_user_profile_fields(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'last_name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
            'age' => 11,
            'height' => 175.5,
            'weight' => 82.4,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'last_name',
                'email',
                'password',
                'age',
                'height',
                'weight',
            ]);
    }
}
