<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Http\Requests\ProfileImageRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\ThemeRequest;
use App\Http\Resources\UserResource;
use App\Helpers\BaseHelper;
use App\Models\EntityLog;
use App\Models\Exercise;
use App\Models\GymUser;
use App\Models\Muscle;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineExerciseLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserProgress;
use App\Models\UserRoutine;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(){
        throw PublicException::notFoundError('No implementado');   
    }
   
    public function store(Request $request){
        throw PublicException::notFoundError('No implementado');   
    }

    public function show(Request $request, string $id): JsonResponse {
        $user = User::find($id);

        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }

        return $this->successApiResponse($this->buildProfileModel($user, $request->user()));
    }
    public function detail(Request $request, string $id): JsonResponse {
        $viewer = $request->user();
        $this->seeAllPermitOrFail($viewer);
        $user = User::find($id);
        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }

        $dbConnector = $this->getDbConnector();
        return $this->successApiResponse($this->buildProfileModel($user, $viewer) + [
            'created_at' => BaseHelper::toDisplayDate($user->created_at),
            'roles' => array_map(fn(Role $role) => $role->getName(), $user->queryRoleList($dbConnector)),
            'gyms' => GymUser::queryGymNameMapByUserIds($dbConnector, [$user->id])[$user->id] ?? [],
            'routines' => UserRoutine::queryRoutineMapByUserIds($dbConnector, [$user->id])[$user->id] ?? [],
            'pending_profile_fields' => $user->getPendingProfileFieldList(),
        ]);
    }

    // registro de actividad del usuario, de mas nuevo a mas viejo; model=routine|exercise|muscle|gym filtra por tipo
    public function logs(Request $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $user = User::find($id);
        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }
        $modelSlug = $request->query('model');
        $refModelId = $modelSlug ? EntityLog::modelIdFromSlug($modelSlug) : null;
        if($modelSlug && !$refModelId){
            throw PublicException::validationError('Tipo de registro invalido: '.$modelSlug);
        }

        $page = max(1, (int)$request->query('page', 1));
        [$entityLogList, $hasMore] = EntityLog::queryPageByUserId($this->getDbConnector(), $user->id, $page, $refModelId);
        return $this->successApiResponse([
            'user' => $user->buildPublicApiModel(),
            'page' => $page,
            'hasMore' => $hasMore,
            'items' => array_map(fn(EntityLog $entityLog) => $entityLog->buildApiModel(), $entityLogList),
        ]);
    }

    public function update(Request $request, string $id){
        throw PublicException::notFoundError('No implementado');   
    }

    public function updateProfile(ProfileRequest $request, string $id): JsonResponse {
        $user = User::find($id);
        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }
        if((int)$request->user()->id !== (int)$user->id){
            throw PublicException::forbiddenError('Solo puedes editar tu propio perfil');
        }

        $profileData = $request->validated();
        // el genero se define una sola vez: despues se ignora si no viene y se rechaza si quiere cambiarlo
        if($user->gender){
            if(isset($profileData['gender']) && $profileData['gender'] !== $user->gender){
                throw PublicException::validationError('El genero ya fue definido y no se puede cambiar');
            }
            unset($profileData['gender']);
        }

        $user->fill($profileData);
        $changedFieldList = array_keys($user->getDirty());
        $user->save();
        if($changedFieldList){
            EntityLog::addNewForRef($this->getDbConnector(), $user->id, CoreModel::userModelId, $user->id, EntityLog::actionIdUpdated, $user->getFullName(), [
                'fields' => $changedFieldList,
            ]);
        }

        return $this->successApiResponse($this->buildProfileModel($user, $user));
    }

    public function updateTheme(ThemeRequest $request): JsonResponse {
        $user = $request->user();
        $user->theme = $request->validated()['theme'];
        $user->save();
        return $this->successApiResponse(['theme' => $user->theme]);
    }

    public function updateProfileImage(ProfileImageRequest $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if(!$user){
            throw PublicException::notFoundError('Usuario no encontrado');
        }

        if((int) $request->user()->id !== (int) $user->id){
            throw PublicException::unAuthorizedError('No puedes cambiar la foto de otro usuario');
        }

        $resourceService = $this->getResourceService();
        $resourceKey = $resourceService->uploadFile($request->file('profile_image'), 'images/profiles');
        $resourceService->deleteFile($user->profile_image_url);

        $user->profile_image_url = $resourceKey;
        $user->save();
        EntityLog::addNewForRef($this->getDbConnector(), $user->id, CoreModel::userModelId, $user->id, EntityLog::actionIdImageUpdated, $user->getFullName());

        return $this->successApiResponse(UserResource::make($user));
    }

    // favoritos calculados con lo que entrena; solo se muestran si quien mira puede verlos (una rutina privada de otro no aparece)
    private function buildProfileModel(User $user, User $viewer): array {
        $dbConnector = $this->getDbConnector();
        $resourceService = $this->getResourceService();
        $isAdmin = $viewer->hasSeeAllPermit($dbConnector);
        $favorites = ['routine' => null, 'muscle' => null, 'exercise' => null];

        $routineId = RoutineExerciseLog::queryFavoriteRoutineId($dbConnector, $user->id);
        $routine = $routineId ? $this->queryVisibleRoutine($viewer, $routineId, $isAdmin) : null;
        if($routine){
            $favorites['routine'] = [
                'id' => $routine->getEntityId(),
                'name' => $routine->getName(),
                'exerciseCount' => count(RoutineExercise::queryExerciseListByRoutineId($dbConnector, $routine->getEntityId())),
            ];
        }

        $muscleId = RoutineExerciseLog::queryFavoriteMuscleId($dbConnector, $user->id);
        $muscle = $muscleId ? Muscle::queryByDbId($dbConnector, $muscleId) : null;
        if($muscle?->canBeSeenBy($viewer->id, $isAdmin)){
            $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::muscleModelId, $muscle->getEntityId());
            $favorites['muscle'] = [
                'id' => $muscle->getEntityId(),
                'name' => $muscle->getName(),
                'kind' => $muscle->getKindSlug(),
                'image_url' => $resourceService->obtainPublicUrl($resource),
            ];
        }

        $exerciseId = RoutineExerciseLog::queryFavoriteExerciseId($dbConnector, $user->id);
        $exercise = $exerciseId ? Exercise::queryByDbId($dbConnector, $exerciseId) : null;
        if($exercise?->canBeSeenBy($viewer->id, $isAdmin)){
            $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());
            $favorites['exercise'] = [
                'id' => $exercise->getEntityId(),
                'name' => $exercise->getName(),
                'image_url' => $resourceService->obtainPublicUrl($resource),
                'imageAttribution' => $resourceService->obtainAttribution($resource),
            ];
        }

        $progress = UserProgress::build($dbConnector, $user->id);
        // quien ya tenia historial recibe sus logros al abrir su perfil, sin esperar a entrenar de nuevo
        if((int)$viewer->id === (int)$user->id){
            UserAchievement::unlockReached($dbConnector, $user, $progress);
        }
        return UserResource::make($user)->resolve() + [
            'favorites' => $favorites,
            'progress' => $progress,
            'achievements' => UserAchievement::buildApiList($dbConnector, $user->id, $progress),
        ];
    }

    private function queryVisibleRoutine(User $viewer, int $routineId, bool $isAdmin): ?Routine {
        $routine = Routine::queryByDbId($this->getDbConnector(), $routineId);
        if(!$routine){
            return null;
        }
        $canSee = $isAdmin || $routine->isOwnedBy($viewer->id) || UserRoutine::isAssigned($this->getDbConnector(), $viewer->id, $routine->getEntityId());
        return $canSee ? $routine : null;
    }

    public function destroy(string $id){
        throw PublicException::notFoundError('No implementado');   
    }
}
