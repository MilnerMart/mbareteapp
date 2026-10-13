<?php

namespace App\Http\Controllers;

use App\Rules\PersonName;
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
        return $this->renderView('login.login');
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
        $roleList = $this->apiClient->getRegisterRoles() ?? [];
        return $this->renderView('login.register', compact('roleList'));
    }

    public function store(Request $request){
        $request->merge([
            'name' => PersonName::normalize($request->input('name')),
            'last_name' => PersonName::normalize($request->input('last_name')),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', new PersonName('nombre')],
            'last_name' => ['required', 'string', 'min:2', 'max:60', new PersonName('apellido')],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
            'age' => ['required', 'integer', 'min:12', 'max:100'],
            'height' => ['required', 'integer', 'min:100', 'max:230'],
            'weight' => ['required', 'integer', 'min:25', 'max:250'],
            'role_id' => ['required', 'integer'],
            'gym_code' => ['nullable', 'string', 'max:50'],
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

        if($response['data']['verificationEmailSent'] ?? false){
            session()->flash('auth_notice', 'Te enviamos un correo a '.$response['data']['user']['email'].' para confirmar tu cuenta.');
        }

        // si quedo alguna solicitud pendiente (entrenador o gimnasio) lo llevamos al perfil para que vea su ticket
        $ticketNumbers = array_column($response['data']['tickets'] ?? [], 'number');
        if($ticketNumbers){
            return redirect()->route('user.profile', $response['data']['user']['id'])
                ->with('register_tickets', 'Cuenta creada. Tu solicitud quedo pendiente de revision: '.implode(', ', $ticketNumbers).'.');
        }

        return redirect()->route('muscle.index');
    }

    public function logout(Request $request){
        $this->apiClient->logout();

        $request->session()->forget(['auth_user', 'auth_token']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('user.login');
    }

    /**
     * Enlace del correo de confirmacion. Funciona con o sin sesion iniciada.
     */
    public function verifyEmail(Request $request){
        $isLoggedIn = $request->session()->has('auth_user');
        $redirect = redirect()->route($isLoggedIn ? 'muscle.index' : 'user.login');
        $params = $request->only(['user', 'expires', 'signature']);
        if(count(array_filter($params)) !== 3){
            return $redirect->with('auth_error', 'El enlace para confirmar tu correo esta incompleto.');
        }

        $response = $this->apiClient->verifyEmail($params);
        if(!$this->isSuccess($response)){
            return $redirect->with('auth_error', $this->errorMessage($response, 'No pudimos confirmar tu correo.'));
        }

        if((int) $request->session()->get('auth_user.id') === (int) $response['data']['userId']){
            $request->session()->put('auth_user.email_verified', true);
        }
        return $redirect->with('auth_notice', 'Listo, tu correo '.$response['data']['email'].' quedo confirmado.');
    }

    public function resendVerificationEmail(Request $request){
        $response = $this->apiClient->resendVerificationEmail();

        if(($response['error']['code'] ?? null) === 'email_verification.already_verified'){
            $request->session()->put('auth_user.email_verified', true);
            return back()->with('auth_notice', 'Tu correo ya estaba confirmado.');
        }
        if(!$this->isSuccess($response)){
            return back()->with('auth_error', $this->errorMessage($response, 'No pudimos reenviar el correo.'));
        }

        return back()->with('auth_notice', 'Te reenviamos el correo a '.$response['data']['email'].'. Revisa tambien la carpeta de spam.');
    }

    public function forgotPassword(){
        return $this->renderView('login.forgot-password');
    }

    public function sendResetLink(Request $request){
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $response = $this->apiClient->forgotPassword($validated['email']);
        if(!$this->isSuccess($response)){
            return back()->withErrors(['email' => $this->errorMessage($response, 'No pudimos enviar el correo.')])->withInput();
        }

        return back()->with('auth_notice', $response['data']['message']);
    }

    public function resetPasswordForm(Request $request){
        $token = (string) $request->query('token', '');
        $email = (string) $request->query('email', '');
        if(strlen($token) !== 64 || !filter_var($email, FILTER_VALIDATE_EMAIL)){
            return redirect()->route('user.password.request')->with('auth_error', 'El enlace para cambiar la contraseña esta incompleto. Pide uno nuevo.');
        }

        return $this->renderView('login.reset-password', compact('token', 'email'));
    }

    public function resetPassword(Request $request){
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
        ]);

        $response = $this->apiClient->resetPassword($validated);
        if(!$this->isSuccess($response)){
            return back()->withErrors(['password' => $this->errorMessage($response, 'No pudimos cambiar la contraseña.')]);
        }

        // las sesiones abiertas de esa cuenta quedan cerradas en el backend
        if(strcasecmp((string) $request->session()->get('auth_user.email'), $response['data']['email']) === 0){
            $request->session()->forget(['auth_user', 'auth_token']);
        }
        return redirect()->route('user.login')->with('auth_notice', 'Tu contraseña se cambio. Ya puedes ingresar con la nueva.');
    }

    private function errorMessage(?array $response, string $fallback): string
    {
        if(isset($response['error']['message'])){
            return $response['error']['message'];
        }

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
        return (bool) ($response['success'] ?? false);
    }
}
