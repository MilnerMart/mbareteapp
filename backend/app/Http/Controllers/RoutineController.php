<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutineAssignRequest;
use App\Http\Requests\RoutineExerciseRequest;
use App\Http\Requests\RoutineRequest;
use App\Http\Resources\UserResource;
use App\Core\CoreModel;
use App\Models\EntityLog;
use App\Models\Exercise;
use App\Models\GymUser;
use App\Models\Permit;
use App\Models\Resource;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineExerciseLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserNotification;
use App\Models\UserProgress;
use App\Models\UserRoutine;
use App\Core\EntityStatus;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoutineController extends Controller
{
    /**
     * Rutinas propias y asignadas al usuario. El admin ve todas.
     */
    public function index(Request $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $assignedIdList = array_map(fn(Routine $routine) => $routine->getEntityId(), Routine::queryListByAssignedUserId($dbConnector, $user->id));

        $routineList = $user->hasSeeAllPermit($dbConnector)
            ? Routine::queryList($dbConnector)
            : array_merge(
                Routine::queryListByOwnerId($dbConnector, $user->id),
                Routine::queryListByAssignedUserId($dbConnector, $user->id)
            );

        $model = [];
        foreach ($routineList as $routine) {
            /**  @var Routine $routine */
            $routineModel = $routine->buildApiModel();
            $routineModel['isAssigned'] = in_array($routine->getEntityId(), $assignedIdList, true);
            $routineModel['canEdit'] = $this->canEditRoutine($user, $routine);
            // una rutina propia que ademas esta asignada no se repite
            $model[$routine->getEntityId()] = $routineModel;
        }
        return $this->successApiResponse(array_values($model));
    }

    public function store(RoutineRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $validated = $request->validated();
        $routine = Routine::allocNew(
            $validated['name'],
            Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            $request->user()->id,
            $validated['frequency'],
            Routine::minutesToSeconds($validated['rest_minutes']),
        );
        $routine->setDescription($validated['description'] ?? null);
        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $routine, $request){
            $routine->writeToDb($dbConnector);
            EntityLog::addNew($dbConnector, $request->user()->id, $routine, EntityLog::actionIdCreated, $routine->getName());
        });

        return $this->successApiResponse($routine->buildApiModel(), 201);
    }

    public function show(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $routine = $this->queryAccessibleRoutineOrFail($user, $id);

        $owner = User::find($routine->getOwnerId());
        $routineModel = $routine->buildApiModel();
        $routineModel['canEdit'] = $this->canEditRoutine($user, $routine);
        $doneWeightMap = RoutineExerciseLog::queryDoneTodayWeightMap($dbConnector, $user->id, $routine->getEntityId());
        $routineModel['exercises'] = array_map(fn(array $exercise) => $exercise + [
            'doneToday' => array_key_exists((int)$exercise['id'], $doneWeightMap),
            'doneWeightKg' => $doneWeightMap[(int)$exercise['id']] ?? null,
        ], $this->addExerciseResources(RoutineExercise::queryExerciseListByRoutineId($dbConnector, $routine->getEntityId())));
        $routineModel['refs']['owner'] = $owner ? UserResource::make($owner) : null;
        if($user->hasSeeAllPermit($dbConnector)){
            $routineModel['assignedUsers'] = $this->buildAssignedUserList($routine);
        }
        return $this->successApiResponse($routineModel);
    }

    public function update(RoutineRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryEditableRoutineOrFail($request, $id);
        $validated = $request->validated();
        $routine->setName($validated['name']);
        $routine->setFrequency($validated['frequency']);
        $routine->setRestTime(Routine::minutesToSeconds($validated['rest_minutes']));
        $routine->setDescription($validated['description'] ?? null);
        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $routine, $validated, $request){
            $routine->writeToDb($dbConnector);
            EntityLog::addNew($dbConnector, $request->user()->id, $routine, EntityLog::actionIdUpdated, $routine->getName());
            if(array_key_exists('exercises', $validated)){
                $exerciseList = $validated['exercises'];
                ksort($exerciseList);
                RoutineExercise::syncRoutineExercises($dbConnector, $routine->getEntityId(), $exerciseList);
            }
        });

        $model = $routine->buildApiModel();
        $model['exercises'] = RoutineExercise::queryExerciseListByRoutineId($dbConnector, $routine->getEntityId());
        return $this->successApiResponse($model);
    }

    public function destroy(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryEditableRoutineOrFail($request, $id);
        $routine->setStatusId(EntityStatus::statusIdDeleted);
        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $routine, $request){
            $routine->writeToDb($dbConnector);
            EntityLog::addNew($dbConnector, $request->user()->id, $routine, EntityLog::actionIdDeleted, $routine->getName());
        });

        return $this->successApiResponse(code: 200);
    }

    public function addExercise(RoutineExerciseRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryEditableRoutineOrFail($request, $id);
        $validated = $request->validated();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector, $validated['exercise_id']);
        // lo privado de otro usuario o lo pendiente de aprobacion no se puede sumar a una rutina
        if(!$exercise->isApproved() || !$exercise->canBeSeenBy($request->user()->id, $request->user()->hasSeeAllPermit($dbConnector))){
            throw PublicException::validationError('El ejercicio no esta disponible para tus rutinas');
        }
        RoutineExercise::addOrUpdateRoutineExercise(
            $dbConnector,
            $routine->getEntityId(),
            $exercise->getEntityId(),
            $validated['sets'] ?? null,
            $validated['reps'] ?? null,
            $validated['duration_minutes'] ?? null,
            $validated['notes'] ?? null,
        );
        EntityLog::addNew($dbConnector, $request->user()->id, $routine, EntityLog::actionIdExerciseAdded, $routine->getName(), [
            'exerciseId' => $exercise->getEntityId(),
            'exerciseName' => $exercise->getName(),
        ]);

        return $this->successApiResponse(RoutineExercise::queryExerciseListByRoutineId($dbConnector, $routine->getEntityId()), 201);
    }

    // lo hecho en el dia se guarda de una vez (lista completa) y alimenta los favoritos del perfil
    public function syncDoneToday(Request $request, string $id): JsonResponse {
        $validated = $request->validate([
            'exercises' => ['present', 'array', 'max:100'],
            'exercises.*.exercise_id' => ['required', 'integer', 'distinct'],
            'exercises.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryAccessibleRoutineOrFail($request->user(), $id);
        $weightKgMap = [];
        foreach ($validated['exercises'] as $exercise) {
            $weightKgMap[(int)$exercise['exercise_id']] = isset($exercise['weight_kg']) ? round((float)$exercise['weight_kg'], 2) : null;
        }
        $exerciseIdList = array_keys($weightKgMap);
        $routineExerciseIdList = RoutineExercise::allocDbTable($dbConnector)->where('routine_id', $routine->getEntityId())
            ->pluck('exercise_id')->map(fn($exerciseId) => (int)$exerciseId)->all();
        if(array_diff($exerciseIdList, $routineExerciseIdList)){
            throw PublicException::validationError('Solo puedes marcar ejercicios que estan en esta rutina');
        }

        $previousRank = UserProgress::build($dbConnector, $request->user()->id)['rank'];
        RoutineExerciseLog::syncDoneToday($dbConnector, $request->user()->id, $routine->getEntityId(), $weightKgMap);
        $progress = UserProgress::build($dbConnector, $request->user()->id);
        $this->addRankUpLog($request->user(), $previousRank, $progress['rank']);
        UserAchievement::unlockReached($dbConnector, $request->user(), $progress);
        return $this->successApiResponse(['doneExerciseIds' => $exerciseIdList]);
    }

    // el marcado del dia no se registra, solo cuando con eso llega por primera vez a un rango mas alto
    private function addRankUpLog(User $user, string $previousRank, string $rank): void {
        $dbConnector = $this->getDbConnector();
        if(!UserProgress::isHigherRank($rank, $previousRank)
            || EntityLog::existsWithData($dbConnector, $user->id, CoreModel::userModelId, EntityLog::actionIdRankUp, 'rank', $rank)){
            return;
        }
        EntityLog::addNewForRef($dbConnector, $user->id, CoreModel::userModelId, $user->id, EntityLog::actionIdRankUp, $user->getFullName(), [
            'rank' => $rank,
            'previousRank' => $previousRank,
            'gender' => $user->gender,
        ]);
    }

    public function removeExercise(Request $request, string $id, string $exerciseId): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryEditableRoutineOrFail($request, $id);
        if(!RoutineExercise::removeRoutineExercise($dbConnector, $routine->getEntityId(), (int)$exerciseId)){
            throw PublicException::notFoundError('El ejercicio no esta en esta rutina');
        }
        EntityLog::addNew($dbConnector, $request->user()->id, $routine, EntityLog::actionIdExerciseRemoved, $routine->getName(), [
            'exerciseId' => (int)$exerciseId,
            'exerciseName' => Exercise::queryByDbId($dbConnector, (int)$exerciseId)?->getName(),
        ]);

        return $this->successApiResponse(code: 200);
    }

    public function assign(RoutineAssignRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $routine = Routine::queryByDbIdOrFail($dbConnector, $id);
        $studentId = (int)$request->validated()['user_id'];
        $this->canAssignRoutineOrFail($user, $routine, $studentId);

        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $studentId, $routine, $user){
            if(UserRoutine::addNewUserRoutine($dbConnector, $studentId, $routine->getEntityId(), $user->id)){
                UserNotification::addRoutineAssigned($dbConnector, $studentId, $routine, $user);
                EntityLog::addNew($dbConnector, $user->id, $routine, EntityLog::actionIdAssigned, $routine->getName(), [
                    'targetUserId' => $studentId,
                    'targetUserName' => User::queryFullNameById($dbConnector, $studentId),
                ]);
            }
        });
        return $this->successApiResponse(code: 201);
    }

    public function unassign(Request $request, string $id, string $userId): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $routine = Routine::queryByDbIdOrFail($dbConnector, $id);
        $this->canAssignRoutineOrFail($user, $routine, (int)$userId);

        if(!UserRoutine::removeUserRoutine($dbConnector, (int)$userId, $routine->getEntityId())){
            throw PublicException::notFoundError('La rutina no esta asignada a este alumno');
        }
        EntityLog::addNew($dbConnector, $user->id, $routine, EntityLog::actionIdUnassigned, $routine->getName(), [
            'targetUserId' => (int)$userId,
            'targetUserName' => User::queryFullNameById($dbConnector, (int)$userId),
        ]);
        return $this->successApiResponse(code: 200);
    }

    /**
     * Usuarios con la rutina asignada y los gimnasios a los que pertenecen.
     */
    /**
     * Imagenes de cada ejercicio para mostrarlas al abrirlo desde la rutina. Viajan con la rutina para que
     * el alumno vea tambien los ejercicios privados que su entrenador le asigno.
     */
    private function addExerciseResources(array $exerciseList): array {
        $dbConnector = $this->getDbConnector();
        foreach ($exerciseList as &$exercise) {
            $exercise['resources'] = [];
            foreach (Resource::queryListByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise['id']) as $resource) {
                $url = $this->getResourceService()->obtainPublicUrl($resource);
                if($url){
                    $exercise['resources'][] = [
                        'url' => $url,
                        'kind' => $resource->buildApiModel()['kind'],
                        'attribution' => $this->getResourceService()->obtainAttribution($resource),
                    ];
                }
            }
        }
        return $exerciseList;
    }

    private function buildAssignedUserList(Routine $routine): array {
        $dbConnector = $this->getDbConnector();
        $userIdList = UserRoutine::queryUserIdListByRoutineId($dbConnector, $routine->getEntityId());
        $gymNameMap = GymUser::queryGymNameMapByUserIds($dbConnector, $userIdList);
        $model = [];
        foreach (User::whereIn('id', $userIdList)->orderBy('name')->get() as $assignedUser) {
            $userModel = UserResource::make($assignedUser)->resolve();
            $userModel['gyms'] = $gymNameMap[$assignedUser->id] ?? [];
            $model[] = $userModel;
        }
        return $model;
    }

    private function queryAccessibleRoutineOrFail(User $user, string $id): Routine {
        $routine = Routine::queryByDbIdOrFail($this->getDbConnector(), $id);
        if(!$this->canEditRoutine($user, $routine) && !UserRoutine::isAssigned($this->getDbConnector(), $user->id, $routine->getEntityId())){
            throw PublicException::forbiddenError('No tienes acceso a esta rutina');
        }
        return $routine;
    }

    private function canEditRoutine(User $user, Routine $routine): bool {
        return $routine->isOwnedBy($user->id) || $user->hasSeeAllPermit($this->getDbConnector());
    }

    private function queryEditableRoutineOrFail(Request $request, string $id): Routine {
        $routine = Routine::queryByDbIdOrFail($this->getDbConnector(), $id);
        if(!$this->canEditRoutine($request->user(), $routine)){
            throw PublicException::forbiddenError('Solo el creador de la rutina puede modificarla');
        }
        return $routine;
    }

    /**
     * El admin asigna cualquier rutina. El entrenador solo sus propias rutinas y a alumnos de sus gimnasios.
     */
    private function canAssignRoutineOrFail(User $user, Routine $routine, int $studentId): void {
        $dbConnector = $this->getDbConnector();
        if($user->hasSeeAllPermit($dbConnector)){
            return;
        }
        if(!$user->hasPermit($dbConnector, Permit::assignRoutinesPermitSlug)){
            throw PublicException::forbiddenError('No tienes permisos para asignar rutinas');
        }
        if(!$routine->isOwnedBy($user->id)){
            throw PublicException::forbiddenError('Solo puedes asignar tus propias rutinas');
        }
        if(!GymUser::isUserInGymOwnedBy($dbConnector, $studentId, $user->id)){
            throw PublicException::forbiddenError('El alumno no pertenece a ninguno de tus gimnasios');
        }
    }
}
