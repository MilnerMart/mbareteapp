<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GymEntityRequest;
use App\Http\Requests\GymImageRequest;
use App\Http\Requests\GymVisibilityRequest;
use App\Http\Resources\UserResource;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Permit;
use App\Models\Resource;
use App\Models\User;
use App\Models\UserRoutine;
use App\PublicException;
use App\Services\ResourceService;
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

    public function updateImage(GymImageRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $gym = $this->queryAccessibleGymOrFail($request, $id);

        $service = new ResourceService($dbConnector);
        $service->saveEntityImage($request->file('gym_image'), 'images/gyms', CoreModel::gymEntityModelId,
            $gym->getEntityId(), $gym->getName(), $gym->getSlug());

        return $this->successApiResponse($this->buildGymModel($gym), 200);
    }

    public function users(Request $request, string $id): JsonResponse {
        $gym = $this->queryAccessibleGymOrFail($request, $id);
        $userIdList = GymUser::queryUserIdListByGymId($this->getDbConnector(), $gym->getEntityId());
        $userList = User::whereIn('id', $userIdList)->get()->sortBy(fn(User $user) => array_search($user->id, $userIdList));
        $routineMap = UserRoutine::queryRoutineMapByUserIds($this->getDbConnector(), $userIdList);

        $model = [];
        foreach ($userList as $user) {
            $userModel = UserResource::make($user)->resolve();
            $userModel['routines'] = $routineMap[$user->id] ?? [];
            $model[] = $userModel;
        }
        return $this->successApiResponse($model);
    }

    public function removeUser(Request $request, string $id, string $userId): JsonResponse {
        $gym = $this->queryAccessibleGymOrFail($request, $id);
        if(!GymUser::removeGymUser($this->getDbConnector(), $gym->getEntityId(), (int)$userId)){
            throw PublicException::notFoundError('El alumno no pertenece a este gimnasio');
        }

        return $this->successApiResponse(code: 200);
    }

    /**
     * Gimnasios a los que pertenece el usuario. No expone el codigo del gimnasio,
     * de los alumnos solo los que eligieron mostrarse, y la cantidad de alumnos solo al admin.
     */
    public function memberIndex(Request $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $user = $this->belongsToGymOrFail($request);
        $isAdmin = $user->hasSeeAllPermit($dbConnector);
        $model = [];
        foreach (GymUser::queryMembershipListByUserId($dbConnector, $request->user()->id) as $membership) {
            $gym = Gym::queryByDbId($dbConnector, $membership['gym_id']);
            if(!$gym){
                continue;
            }
            $gymModel = $this->buildGymModel($gym);
            unset($gymModel['slug']);
            if(!$isAdmin){
                unset($gymModel['alumnsCount']);
            }
            $owner = User::find($gym->getOwnerId());
            $gymModel['refs']['owner'] = $owner?->buildPublicApiModel();
            $gymModel['isPublic'] = $membership['is_public'];
            $publicUserIdList = GymUser::queryPublicUserIdListByGymId($dbConnector, $gym->getEntityId());
            $gymModel['publicStudents'] = User::whereIn('id', $publicUserIdList)->orderBy('name')->get()
                ->map(fn(User $student) => $student->buildPublicApiModel())->all();
            $model[] = $gymModel;
        }
        return $this->successApiResponse($model);
    }

    public function updateMemberVisibility(GymVisibilityRequest $request, string $id): JsonResponse {
        $this->belongsToGymOrFail($request);
        $isPublic = (bool)$request->validated()['is_public'];
        if(!GymUser::updateVisibility($this->getDbConnector(), (int)$id, $request->user()->id, $isPublic)){
            throw PublicException::notFoundError('No perteneces a este gimnasio');
        }
        return $this->successApiResponse(['isPublic' => $isPublic]);
    }

    private function buildGymModel(Gym $gym): array {
        $dbconnector = $this->getDbConnector();
        $userOwner = User::find($gym->getOwnerId());
        $resource = Resource::queryByOwnerAndModelId($dbconnector, CoreModel::gymEntityModelId, $gym->getEntityId());
        $gymModel = $gym->buildApiModel();
        $gymModel['image_url'] = $resource?->getPublicUrl();
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

    /**
     * Alumnos y entrenadores ven los gimnasios a los que pertenecen.
     */
    private function belongsToGymOrFail(Request $request): User {
        /**  @var User $user */
        $user = $request->user();
        if(!$user->hasPermit($this->getDbConnector(), Permit::belongsToGymPermitSlug)){
            throw PublicException::forbiddenError('No tienes permisos para ver tus gimnasios');
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
