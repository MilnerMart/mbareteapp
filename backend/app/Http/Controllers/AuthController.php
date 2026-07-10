<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create($validated);
        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        return $this->successApiResponse(new AuthResource(UserResource::make($user), $token), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no son correctas.',
            ]);
        }

        $user->tokens()->where('name', $this->tokenName($request))->delete();
        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        return $this->successApiResponse(new AuthResource(UserResource::make($user), $token));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successApiResponse(UserResource::make($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->successApiResponse(code: 200);
    }

    private function tokenName(Request $request): string
    {
        return $request->userAgent() ?: 'mbarete-app';
    }
}
