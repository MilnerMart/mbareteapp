<?php

namespace App\Support;

use App\Services\ApiClientService;
use Illuminate\Support\Facades\Cookie;

/**
 * Tema claro u oscuro. Manda el que eligio el usuario en su cuenta; sin sesion, el de la cookie de este navegador;
 * si no hay ninguno, el oscuro de siempre.
 */
class ThemePreference
{
    const cookieName = 'theme';

    // la cookie dura un año
    const cookieMinutes = 525600;

    const themeList = ['dark', 'light'];

    const defaultTheme = 'dark';

    static function current(): string
    {
        $theme = session('auth_user.theme') ?? request()->cookie(self::cookieName);
        return in_array($theme, self::themeList, true) ? $theme : self::defaultTheme;
    }

    static function buildView(): array
    {
        $isLight = self::current() === 'light';
        return [
            'name' => $isLight ? 'light' : 'dark',
            'navbarClass' => $isLight ? 'navbar-light' : 'navbar-dark',
            'isLight' => $isLight,
            'nextTheme' => $isLight ? 'dark' : 'light',
            // interruptor "Modo claro": encendido = claro
            'switchChecked' => $isLight ? 'true' : 'false',
            'switchTitle' => $isLight ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro',
        ];
    }

    static function remember(string $theme): void
    {
        Cookie::queue(self::cookieName, $theme, self::cookieMinutes);
    }

    // al entrar: si la cuenta ya tiene tema, este navegador lo adopta; si no, la cuenta se queda con el que eligio aca
    static function syncAfterLogin(ApiClientService $apiClient): void
    {
        $accountTheme = session('auth_user.theme');
        if(in_array($accountTheme, self::themeList, true)){
            self::remember($accountTheme);
            return;
        }
        $browserTheme = request()->cookie(self::cookieName);
        if(in_array($browserTheme, self::themeList, true) && ($apiClient->updateTheme($browserTheme)['success'] ?? false)){
            session()->put('auth_user.theme', $browserTheme);
        }
    }
}
