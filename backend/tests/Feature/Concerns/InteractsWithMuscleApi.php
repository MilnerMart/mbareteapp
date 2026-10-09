<?php

namespace Tests\Feature\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Levanta la base en sqlite en memoria (incluida la conexion muscleDb que usan los modelos)
 * y da helpers para registrar, loguear y cambiar de usuario dentro de un test.
 */
trait InteractsWithMuscleApi
{
    protected const adminEmail = 'admin@example.com', password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();
        // los modelos usan la conexion muscleDb (mysql): en el test comparte el sqlite en memoria
        config(['database.connections.muscleDb' => config('database.connections.sqlite')]);
        DB::purge('muscleDb');
        DB::connection('muscleDb')->setPdo(DB::connection()->getPdo());
        putenv('ADMIN_EMAIL='.static::adminEmail);
        putenv('ADMIN_PASSWORD='.static::password);
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function register(string $email, string $roleSlug, ?string $gymCode = null)
    {
        return $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => static::password,
            'password_confirmation' => static::password,
            'age' => 30,
            'height' => 175,
            'weight' => 80,
            'role_id' => DB::table('roles')->where('slug', $roleSlug)->value('id'),
            'gym_code' => $gymCode,
        ]);
    }

    /**
     * El guard de sanctum recuerda al usuario entre requests del mismo test: se olvida al cambiar de token.
     */
    protected function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($token);
    }

    protected function login(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => static::password])
            ->json('data.token');
    }
}
