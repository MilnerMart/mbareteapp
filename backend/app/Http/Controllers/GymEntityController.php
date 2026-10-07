<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GymEntityRequest;
use App\Http\Resources\UserResource;
use App\Models\Gym;
use App\Models\Permit;
use App\Models\Resource;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymEntityController extends Controller {


    public function index(Request $request): JsonResponse {
        $dbconnector = $this->getDbConnector();
        $user = $this->canManageGymsOrFail($request);
        $gymList = $user->hasSeeAllPermit($dbconnector)
            ? Gym::queryList($dbconnector)
            : Gym::queryListByOwnerId($dbconnector, $user->id);
        $model=[];
        foreach ($gymList as $gym) {
            /**  @var Gym $gym */
            //!refactor a como obtenemos los recurso para evitar n + 1
            $model[]= $this->buildGymModel($gym);
        }
        return $this->successApiResponse($model);
    }

    public function show(Request $request, string $id): JsonResponse {
        $gym = $this->queryAccessibleGymOrFail($request, $id);
        return $this->successApiResponse($this->buildGymModel($gym));
    }

    public function store(GymEntityRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $user = $this->canManageGymsOrFail($request);
        $validated = $request->validated();
        $gym = Gym::allocNew(
            $validated['name'],
            $validated['slug'],
            $this->resolveOwnerId($user, $validated),
        );
        $gym->writeToDb($dbConnector);

        return $this->successApiResponse($gym->buildApiModel(), 201);
    }

    public function update(GymEntityRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $gym = $this->queryAccessibleGymOrFail($request, $id);
        $validated = $request->validated();
        $ownerId = $this->resolveOwnerId($request->user(), $validated, $gym->getOwnerId());
        $dirty = false;
        if($gym->getName() !== $validated['name']){
            $gym->setName($validated['name']);
            $dirty = true;
        }
        if($gym->getSlug() !== $validated['slug']){
            $gym->setSlug($validated['slug']);
            $dirty = true;
        }
        if($gym->getOwnerId() !== $ownerId){
            $gym->setOwnerId($ownerId);
            $dirty = true;
        }
        if($dirty){
            $gym->writeToDb($dbConnector);
        }

        return $this->successApiResponse($this->buildGymModel($gym), 200);
    }

    private function buildGymModel(Gym $gym): array {
        $dbconnector = $this->getDbConnector();
        $userOwner = User::find($gym->getOwnerId());
        $resource = Resource::queryByOwnerAndModelId($dbconnector, CoreModel::gymEntityModelId, $gym->getEntityId());
        $gymModel = $gym->buildApiModel();
        $gymModel['image_url'] = $resource?->getUrl() ?? null;
        $gymModel['refs']['owner'] = $userOwner ? UserResource::make($userOwner) : null;
        return $gymModel;
    }

    private function canManageGymsOrFail(Request $request): User {
        /**  @var User $user */
        $user = $request->user();
        if(!$user->hasPermit($this->getDbConnector(), Permit::createGymEntityPermitSlug)){
            throw PublicException::forbiddenError('No tienes permisos para gestionar gimnasios');
        }
        return $user;
    }

    private function queryAccessibleGymOrFail(Request $request, string $id): Gym {
        $dbConnector = $this->getDbConnector();
        $user = $this->canManageGymsOrFail($request);
        $gym = Gym::queryByDbIdOrFail($dbConnector, $id);
        if(!$gym->isOwnedBy($user->id) && !$user->hasSeeAllPermit($dbConnector)){
            throw PublicException::forbiddenError('No tienes permisos sobre este gimnasio');
        }
        return $gym;
    }

    /**
     * Solo el admin puede asignar el gimnasio a otro usuario.
     */
    private function resolveOwnerId(User $user, array $validated, ?int $currentOwnerId = null): int {
        if(!empty($validated['owner_id']) && $user->hasSeeAllPermit($this->getDbConnector())){
            return (int)$validated['owner_id'];
        }
        return $currentOwnerId ?? $user->id;
    }

}
