<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\ThemePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    // con sesion se guarda en la cuenta (sigue al usuario en cualquier dispositivo); siempre queda en este navegador
    public function update(Request $request): RedirectResponse{
        $validated = $request->validate([
            'theme' => ['required', Rule::in(ThemePreference::themeList)],
        ]);

        ThemePreference::remember($validated['theme']);
        if(session('auth_token')){
            $this->apiClient->updateTheme($validated['theme']);
            $request->session()->put('auth_user.theme', $validated['theme']);
        }

        return back();
    }
}
