<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GymEntityRequest;
use App\Http\Requests\GymImageRequest;
use App\Http\Requests\GymJoinRequest;
use App\Http\Requests\GymVisibilityRequest;
use App\Http\Resources\UserResource;
use App\Models\EntityLog;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Permit;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserRoutine;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $ownerId = $this->resolveOwnerId($user, $validated);
        $this->gymLimitOrFail($ownerId);
        $gym = Gym::allocNew(
            $validated['name'],
            $validated['slug'],
            $ownerId,
        );
        $gym->writeToDb($dbConnector);
        EntityLog::addNew($dbConnector, $user->id, $gym, EntityLog::actionIdCreated, $gym->getName());

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
            $this->gymLimitOrFail($ownerId);
            $gym->setOwnerId($ownerId);
            $dirty = true;
        }
        if($dirty){
            $gym->writeToDb($dbConnector);
            EntityLog::addNew($dbConnector, $request->user()->id, $gym, EntityLog::actionIdUpdated, $gym->getName());
        }

        return $this->successApiResponse($this->buildGymModel($gym), 200);
    }

    public function updateImage(GymImageRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $gym = $this->queryAccessibleGymOrFail($request, $id);

        $this->getResourceService()->saveEntityImage($request->file('gym_image'), 'images/gyms', CoreModel::gymEntityModelId,
            $gym->getEntityId(), $gym->getName(), $gym->getSlug());
        EntityLog::addNew($dbConnector, $request->user()->id, $gym, EntityLog::actionIdImageUpdated, $gym->getName());

        return $this->successApiResponse($this->buildGymModel($gym), 200);
    }

    public function users(Request $request, string $id): JsonResponse {
        $gym = $this->queryAccessibleGymOrFail($request, $id);
        $userIdList = GymUser::queryUserIdListByGymId($this->getDbConnector(), $gym->getEntityId());
        $userList = User::whereIn('id', $userIdList)->get()->sortBy(fn(User $user) => array_search($user->id, $userIdList));
        $viewer = $request->user();
        $routineMap = UserRoutine::queryRoutineMapByUserIds($this->getDbConnector(), $userIdList,
            $viewer->hasSeeAllPermit($this->getDbConnector()) ? null : $viewer->id);

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
        EntityLog::addNew($this->getDbConnector(), $request->user()->id, $gym, EntityLog::actionIdMemberRemoved, $gym->getName(), [
            'targetUserId' => (int)$userId,
            'targetUserName' => User::queryFullNameById($this->getDbConnector(), (int)$userId),
        ]);

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
            // el front decide si mostrarlo; el codigo no se expone
            $gymModel['isBase'] = $gym->getSlug() === Gym::baseGymSlug;
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

    // el alumno pide entrar a un gimnasio con su codigo: queda un ticket para que el dueño lo acepte
    public function requestJoin(GymJoinRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $user = $this->belongsToGymOrFail($request);
        $gymCode = Str::lower(trim($request->validated()['gym_code']));
        $gym = Gym::queryBySlug($dbConnector, $gymCode)
            ?? throw PublicException::validationError('No existe un gimnasio con el codigo: '.$gymCode);
        if($gym->getSlug() === Gym::baseGymSlug || GymUser::isMember($dbConnector, $gym->getEntityId(), $user->id)){
            throw PublicException::validationError('Ya perteneces a '.$gym->getName());
        }
        if($gym->isOwnedBy($user->id)){
            throw PublicException::validationError('Eres el entrenador de '.$gym->getName());
        }
        // de momento solo se pide desde el gimnasio base: quien ya esta en otro no puede sumar uno mas
        if(GymUser::hasNonBaseMembership($dbConnector, $user->id)){
            throw PublicException::validationError('Ya perteneces a un gimnasio. Por ahora solo puedes estar en uno');
        }
        $pendingTicket = Ticket::queryPendingGymJoin($dbConnector, $user->id);
        if($pendingTicket){
            throw PublicException::validationError('Ya tienes la solicitud '.$pendingTicket->getTicketNumber().' esperando respuesta del entrenador');
        }

        $ticket = $dbConnector->getEnvConecction()->transaction(
            fn() => Ticket::addNewTicket($dbConnector, Ticket::typeIdGymJoin, $user->id, $gym->getEntityId())
        );
        return $this->successApiResponse(Ticket::buildApiModelList($dbConnector, [$ticket])[0], 201);
    }

    public function updateMemberVisibility(GymVisibilityRequest $request, string $id): JsonResponse {
        $this->belongsToGymOrFail($request);
        $isPublic = (bool)$request->validated()['is_public'];
        if(!GymUser::updateVisibility($this->getDbConnector(), (int)$id, $request->user()->id, $isPublic)){
            throw PublicException::notFoundError('No perteneces a este gimnasio');
        }
        $gym = Gym::queryByDbId($this->getDbConnector(), (int)$id);
        if($gym){
            EntityLog::addNew($this->getDbConnector(), $request->user()->id, $gym, EntityLog::actionIdVisibilityChanged, $gym->getName(), [
                'isPublic' => $isPublic,
            ]);
        }
        return $this->successApiResponse(['isPublic' => $isPublic]);
    }

    private function buildGymModel(Gym $gym): array {
        $dbconnector = $this->getDbConnector();
        $userOwner = User::find($gym->getOwnerId());
        $resource = Resource::queryByOwnerAndModelId($dbconnector, CoreModel::gymEntityModelId, $gym->getEntityId());
        $gymModel = $gym->buildApiModel();
        $gymModel['image_url'] = $this->getResourceService()->obtainPublicUrl($resource);
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
    private function gymLimitOrFail(int $ownerId): void {
        $dbConnector = $this->getDbConnector();
        $owner = User::find($ownerId);
        if(!$owner || $owner->hasSeeAllPermit($dbConnector)){
            return;
        }
        if(count(Gym::queryListByOwnerId($dbConnector, $ownerId)) >= Gym::maxGymsPerTrainer){
            throw PublicException::validationError('Por ahora cada entrenador puede tener un solo gimnasio');
        }
    }

    private function resolveOwnerId(User $user, array $validated, ?int $currentOwnerId = null): int {
        if(!empty($validated['owner_id']) && $user->hasSeeAllPermit($this->getDbConnector())){
            return (int)$validated['owner_id'];
        }
        return $currentOwnerId ?? $user->id;
    }

}
