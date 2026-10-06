<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $dbConnector = $this->getDbConnector();
        $validated = $request->validated();
        $role = Role::queryByDbIdOrFail($dbConnector, $validated['role_id']);
        if(!$role->isPublicRegisterRole()){
            throw PublicException::validationError('El rol seleccionado no esta disponible para el registro');
        }

        $user = User::registerNew($dbConnector, $validated, $role);
        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        return $this->successApiResponse(new AuthResource(UserResource::make($user), $token), 201);
    }

    public function registerRoles(): JsonResponse
    {
        $roleList = Role::queryPublicRegisterList($this->getDbConnector());
        $model = [];
        foreach ($roleList as $role) {
            /**  @var Role $role */
            $model[] = $role->buildApiModel();
        }
        return $this->successApiResponse($model);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw PublicException::validationError('Las credenciales ingresadas no son correctas');
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
