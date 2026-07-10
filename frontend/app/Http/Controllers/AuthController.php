<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function login(Request $request){
        return view('login.login');
    }

    public function authenticate(Request $request){
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $response = $this->apiClient->login($validated);

        if(!$this->isSuccess($response)){
            return back()
                ->withErrors(['email' => $this->errorMessage($response, 'No pudimos iniciar sesion.')])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user', $response['data']['user']);
        $request->session()->put('auth_token', $response['data']['token']);

        return redirect()->route('muscle.index');
    }

    public function register(Request $request){
        return view('login.register');
    }

    public function store(Request $request){
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
            'age' => ['required', 'integer', 'min:12', 'max:100'],
            'height' => ['required', 'integer', 'min:100', 'max:230'],
            'weight' => ['required', 'integer', 'min:25', 'max:250'],
        ]);

        $response = $this->apiClient->register($validated);

        if(!$this->isSuccess($response)){
            return back()
                ->withErrors(['email' => $this->errorMessage($response, 'No pudimos crear la cuenta.')])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user', $response['data']['user']);
        $request->session()->put('auth_token', $response['data']['token']);

        return redirect()->route('muscle.index');
    }

    public function logout(Request $request){
        $this->apiClient->logout();

        $request->session()->forget(['auth_user', 'auth_token']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('user.login');
    }

    private function errorMessage(?array $response, string $fallback): string
    {
        if(isset($response['message'])){
            return $response['message'];
        }

        $firstError = $response['errors'] ?? null;

        if(is_array($firstError)){
            $fieldErrors = reset($firstError);
            if(is_array($fieldErrors)){
                return $fieldErrors[0] ?? $fallback;
            }
        }

        return $fallback;
    }

    private function isSuccess(?array $response): bool
    {
        return (bool) (($response['success'] ?? false) || ($response['sucess'] ?? false));
    }
}
