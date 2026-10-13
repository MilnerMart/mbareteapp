<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class RegisterNameValidationTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_register_rejects_emails_numbers_and_symbols_as_names(): void
    {
        foreach (['david.britez94@gmail.com', 'juan123', 'x', '<script>'] as $name) {
            $this->postJson('/api/v1/auth/register', $this->registerData($name.'@example.com', $name, 'Britez'))
                ->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->postJson('/api/v1/auth/register', $this->registerData('apellido@example.com', 'David', 'britez_94'))
            ->assertUnprocessable()->assertJsonValidationErrors('last_name');
    }

    public function test_register_accepts_real_names_and_collapses_spaces(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerData('maria@example.com', '  María   José ', "O'Brien-Núñez"))
            ->assertSuccessful();

        $this->assertSame(['María José', "O'Brien-Núñez"], array_values((array)DB::table('users')->where('email', 'maria@example.com')->first(['name', 'last_name'])));
    }

    private function registerData(string $email, string $name, string $lastName): array
    {
        return [
            'name' => $name,
            'last_name' => $lastName,
            'email' => $email,
            'password' => static::password,
            'password_confirmation' => static::password,
            'age' => 30,
            'height' => 175,
            'weight' => 80,
            'role_id' => DB::table('roles')->where('slug', 'trainee-role')->value('id'),
        ];
    }
}
