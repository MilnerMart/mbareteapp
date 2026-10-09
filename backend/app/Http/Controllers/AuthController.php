<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Gym;
use App\Models\Role;
use App\Models\Ticket;
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

        $baseGym = Gym::queryBySlug($dbConnector, Gym::baseGymSlug)
            ?? throw PublicException::internalError('No se encuentra el gimnasio base');
        // todos entran al gimnasio base; con codigo de otro gimnasio, ademas, el dueño tiene que aceptarlo
        $requestedGym = $this->queryRequestedGymOrFail($validated['gym_code'] ?? null);
        if($requestedGym?->getEntityId() === $baseGym->getEntityId()){
            $requestedGym = null;
        }
        $traineeRole = Role::queryBySlugOrFail($dbConnector, Role::traineeRoleSlug);

        $user = User::registerNew($dbConnector, $validated, $traineeRole, $baseGym, $requestedGym, $role->isTrainerRole());
        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        $ticketModel = Ticket::buildApiModelList($dbConnector, Ticket::queryListByRequesterId($dbConnector, $user->id));

        return $this->successApiResponse(new AuthResource($this->buildAuthUserModel($user), $token, $ticketModel), 201);
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

        return $this->successApiResponse(new AuthResource($this->buildAuthUserModel($user), $token));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successApiResponse($this->buildAuthUserModel($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->successApiResponse(code: 200);
    }


    private function queryRequestedGymOrFail(?string $gymCode): ?Gym {
        if(!$gymCode){
            return null;
        }
        return Gym::queryBySlug($this->getDbConnector(), $gymCode)
            ?? throw PublicException::validationError('No existe un gimnasio con el codigo: '.$gymCode);
    }

    
    private function buildAuthUserModel(User $user): array {
        $dbConnector = $this->getDbConnector();
        $model = UserResource::make($user)->resolve();
        $model['roles'] = array_map(fn(Role $role) => $role->getSlug(), $user->queryRoleList($dbConnector));
        $model['permits'] = $user->queryPermitList($dbConnector);
        return $model;
    }

    private function tokenName(Request $request): string {
        return $request->userAgent() ?: 'mbarete-app';
    }
}
