<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Http\Requests\ProfileImageRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\Resource;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineExerciseLog;
use App\Models\User;
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

        $user->fill($request->validated());
        $user->save();

        return $this->successApiResponse($this->buildProfileModel($user, $user));
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

        return UserResource::make($user)->resolve() + [
            'favorites' => $favorites,
            'progress' => UserProgress::build($dbConnector, $user->id),
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
