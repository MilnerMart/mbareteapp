<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutineAssignRequest;
use App\Http\Requests\RoutineExerciseRequest;
use App\Http\Requests\RoutineRequest;
use App\Http\Resources\UserResource;
use App\Core\CoreModel;
use App\Models\Exercise;
use App\Models\GymUser;
use App\Models\Permit;
use App\Models\Resource;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\User;
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
        $routine->writeToDb($dbConnector);

        return $this->successApiResponse($routine->buildApiModel(), 201);
    }

    public function show(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $routine = Routine::queryByDbIdOrFail($dbConnector, $id);
        if(!$this->canEditRoutine($user, $routine) && !UserRoutine::isAssigned($dbConnector, $user->id, $routine->getEntityId())){
            throw PublicException::forbiddenError('No tienes acceso a esta rutina');
        }

        $owner = User::find($routine->getOwnerId());
        $routineModel = $routine->buildApiModel();
        $routineModel['canEdit'] = $this->canEditRoutine($user, $routine);
        $routineModel['exercises'] = $this->addExerciseResources(
            RoutineExercise::queryExerciseListByRoutineId($dbConnector, $routine->getEntityId()));
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
        $routine->writeToDb($dbConnector);

        return $this->successApiResponse($routine->buildApiModel());
    }

    public function destroy(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryEditableRoutineOrFail($request, $id);
        $routine->setStatusId(EntityStatus::statusIdDeleted);
        $routine->writeToDb($dbConnector);

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
        );

        return $this->successApiResponse(RoutineExercise::queryExerciseListByRoutineId($dbConnector, $routine->getEntityId()), 201);
    }

    public function removeExercise(Request $request, string $id, string $exerciseId): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $routine = $this->queryEditableRoutineOrFail($request, $id);
        if(!RoutineExercise::removeRoutineExercise($dbConnector, $routine->getEntityId(), (int)$exerciseId)){
            throw PublicException::notFoundError('El ejercicio no esta en esta rutina');
        }

        return $this->successApiResponse(code: 200);
    }

    public function assign(RoutineAssignRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $routine = Routine::queryByDbIdOrFail($dbConnector, $id);
        $studentId = (int)$request->validated()['user_id'];
        $this->canAssignRoutineOrFail($user, $routine, $studentId);

        UserRoutine::addNewUserRoutine($dbConnector, $studentId, $routine->getEntityId(), $user->id);
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
            $exercise['resources'] = array_map(
                fn(Resource $resource) => $resource->getPublicUrl(),
                Resource::queryListByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise['id'])
            );
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
