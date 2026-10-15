<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    private array $requestList = [];

    private ?string $accountTheme = null;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return match (true) {
                str_ends_with($path, '/auth/login') => Http::response(['success' => true, 'data' => [
                    'token' => 'token',
                    'user' => ['id' => 1, 'name' => 'Ana', 'last_name' => 'Paz', 'email' => 'ana@example.com', 'permits' => [], 'theme' => $this->accountTheme],
                ]]),
                str_ends_with($path, '/me/theme') => Http::response(['success' => true, 'data' => ['theme' => $request->data()['theme'] ?? null]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_dark_is_the_default_and_the_menu_offers_light(): void
    {
        $this->get('/muscle')->assertOk()
            ->assertSee('<html lang="es" data-theme="dark">', false)
            ->assertSee('navbar-dark', false)
            ->assertSeeInOrder(['logo-container', 'data-theme-switch', 'name="theme" value="light"', 'role="switch"', 'aria-checked="false"', 'fa-moon', 'fa-sun', 'navbar-toggler'], false)
            ->assertSee('title="Cambiar a modo claro"', false);
    }

    public function test_a_guest_choice_stays_in_this_browser(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class)->from('/muscle')->post('/theme', ['theme' => 'light'])
            ->assertRedirect('/muscle')->assertCookie('theme', 'light');
        $this->assertEmpty(array_filter($this->requestList, fn(array $request) => str_ends_with($request[1], '/me/theme')));

        $this->withCookie('theme', 'light')->get('/muscle')->assertOk()
            ->assertSee('data-theme="light"', false)
            ->assertSee('navbar-light', false)
            ->assertSee('name="theme" value="dark"', false)
            ->assertSee('class="theme-switch is-light"', false)
            ->assertSee('aria-checked="true"', false)
            ->assertSee('title="Cambiar a modo oscuro"', false);

        $this->withoutMiddleware(VerifyCsrfToken::class)->from('/muscle')->post('/theme', ['theme' => 'sepia'])->assertSessionHasErrors('theme');
    }

    public function test_a_logged_in_choice_is_saved_in_the_account_and_wins_over_the_browser(): void
    {
        $session = ['auth_user' => ['id' => 1, 'permits' => [], 'theme' => 'dark'], 'auth_token' => 'token'];

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession($session)->from('/muscle')->post('/theme', ['theme' => 'light'])
            ->assertRedirect('/muscle')->assertCookie('theme', 'light')->assertSessionHas('auth_user.theme', 'light');
        $this->assertContains(['PUT', '/api/v1/me/theme', ['theme' => 'light']], $this->requestList);

        $this->withCookie('theme', 'light')->withSession($session)->get('/muscle')->assertOk()->assertSee('data-theme="dark"', false);
    }

    public function test_login_brings_the_account_theme_or_saves_the_browser_one(): void
    {
        $this->accountTheme = 'light';
        $this->withoutMiddleware(VerifyCsrfToken::class)->post('/login', ['email' => 'ana@example.com', 'password' => 'password123'])
            ->assertRedirect(route('muscle.index'))->assertCookie('theme', 'light');

        $this->accountTheme = null;
        $this->requestList = [];
        $this->withoutMiddleware(VerifyCsrfToken::class)->withCookie('theme', 'light')
            ->post('/login', ['email' => 'ana@example.com', 'password' => 'password123'])
            ->assertSessionHas('auth_user.theme', 'light');
        $this->assertContains(['PUT', '/api/v1/me/theme', ['theme' => 'light']], $this->requestList);
    }
}
