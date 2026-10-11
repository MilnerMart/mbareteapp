<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Http\Requests\ExerciseRequest;
use App\Http\Requests\PublicResourceAttachRequest;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\Permit;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $model = [];
        foreach (Exercise::queryVisibleList($dbConnector, $this->queryViewer($request)?->id) as $exercise) {
            /**  @var Exercise $exercise */
            $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());
            $exerciseModel = $exercise->buildApiModel();
            $exerciseModel['image'] = $this->getResourceService()->obtainPublicUrl($resource);
            $model[] = $exerciseModel;
        }
        return $this->successApiResponse($model);
    }

    /**
     * El admin lo publica directo; el entrenador lo propone y queda pendiente hasta que el admin aprueba su ticket.
     */
    public function store(ExerciseRequest $request): JsonResponse {
        $user = $this->createCatalogPermitOrFail($request->user());
        $isAdmin = $this->isAdminViewer($user);
        $validated = $request->validated();
        $dbConnector = $this->getDbConnector();
        $this->queryVisibleMuscleOrFail($request, (int)$validated['muscle_id']);
        $exercise = Exercise::allocNew(
            $validated['name'],
            $validated['slug'] ?? Str::slug($validated['name']),
            $validated['muscle_id'],
            $validated['recommended_rest_time'],
            $validated['description'],
        );
        $exercise->setOwnerId($user->id);
        $exercise->setIsPublic((bool)($validated['is_public'] ?? true));
        $exercise->setReviewState($isAdmin ? Ticket::stateIdApproved : Ticket::stateIdPending);

        [$resource, $ticket] = $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $request, $exercise, $user, $isAdmin){
            $exercise->writeToDb($dbConnector);
            $resource = $this->saveExerciseResources($request, $exercise);
            $ticket = $isAdmin ? null
                : Ticket::addNewCatalogTicket($dbConnector, Ticket::typeIdExerciseCreate, $user->id, $exercise->getEntityId());
            return [$resource, $ticket];
        });

        $model = $exercise->buildApiModel();
        $model['image_url'] = $this->getResourceService()->obtainPublicUrl($resource);
        $model['ticket'] = $ticket ? Ticket::buildApiModelList($dbConnector, [$ticket])[0] : null;
        return $this->successApiResponse($model, 201);
    }

    public function show(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $exercise = $this->queryVisibleExerciseOrFail($request, (int)$id);
        $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());

        $model = $exercise->buildApiModel();
        $model['image_url'] = $this->getResourceService()->obtainPublicUrl($resource);
        $model['canManageResources'] = $this->canManageExerciseResources($request->user('sanctum'), $exercise);
        return $this->successApiResponse($model, 200);
    }

    public function update(ExerciseRequest $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);
        $validated = $request->validated();

        $exercise->setName($validated['name']);
        if(!empty($validated['slug'])){
            $exercise->setSlug($validated['slug']);
        }
        $exercise->setMuscleId((int)$validated['muscle_id']);
        $exercise->setDescription($validated['description']);
        $exercise->setRestTime((int)$validated['recommended_rest_time']);
        if(isset($validated['is_public'])){
            $exercise->setIsPublic((bool)$validated['is_public']);
        }
        $exercise->writeToDb($dbConnector);

        $resource = $this->saveExerciseResources($request, $exercise);
        $resource ??= Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());

        $model = $exercise->buildApiModel();
        $model['image_url'] = $this->getResourceService()->obtainPublicUrl($resource);
        return $this->successApiResponse($model);
    }

    public function destroy(Request $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);

        $exercise->setStatusId(EntityStatus::statusIdInactive);
        $exercise->writeToDb($dbConnector);
        return $this->successApiResponse(code: 200);
    }

    public function getExerciseGroup(Request $request, int $muscleId): JsonResponse{
        $dbConnector = $this->getDbConnector();
        $this->queryVisibleMuscleOrFail($request, $muscleId);
        $exerciseGroup = Exercise::queryVisibleList($dbConnector, $this->queryViewer($request)?->id, $muscleId);
        $model=[];
        foreach ($exerciseGroup as $exercise) {
            /**  @var Exercise $exercise */
            $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());
            $exerciseModel = $exercise->buildApiModel();
            $exerciseModel['image'] = $this->getResourceService()->obtainPublicUrl($resource);
            $model[]= $exerciseModel;
        }
        return $this->successApiResponse($model);
    }

    public function getResources(Request $request, int $exerciseId): JsonResponse{

        $dbConnector = $this->getDbConnector();
        $exercise = $this->queryVisibleExerciseOrFail($request, $exerciseId);

        $model = [];
        foreach (Resource::queryListByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId()) as $resource) {
            /**  @var Resource $resource */
            $resourceModel = $resource->buildApiModel();
            $resourceModel['url'] = $this->getResourceService()->obtainPublicUrl($resource);
            if($resourceModel['url']){
                $model[] = $resourceModel;
            }
        }
        return $this->successApiResponse($model);
    }

    public function addResource(PublicResourceAttachRequest $request, string $id): JsonResponse {
        $exercise = Exercise::queryByDbIdOrFail($this->getDbConnector(), (int)$id);
        $this->manageExerciseResourcesOrFail($request->user(), $exercise);

        $resource = $this->getResourceService()->attachPublicResource(CoreModel::exerciseModelId, $exercise->getEntityId(), $request->validated()['key']);
        $model = $resource->buildApiModel();
        $model['url'] = $this->getResourceService()->obtainPublicUrl($resource);
        return $this->successApiResponse($model, 201);
    }

    public function removeResource(Request $request, string $id, string $resourceId): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector, (int)$id);
        $this->manageExerciseResourcesOrFail($request->user(), $exercise);

        $resource = Resource::queryByDbId($dbConnector, (int)$resourceId);
        if(!$resource || $resource->getEntityModelId() !== CoreModel::exerciseModelId || $resource->getOwnerId() !== $exercise->getEntityId()){
            throw PublicException::notFoundError('No se encuentra el recurso en este ejercicio');
        }
        $mainResource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());
        if(!$resource->isPublicResource() || $mainResource?->getEntityId() === $resource->getEntityId()){
            throw PublicException::validationError('Solo se pueden quitar recursos publicos extra; la imagen principal se cambia editando el ejercicio');
        }
        $resource->setStatusId(EntityStatus::statusIdDeleted);
        $resource->writeToDb($dbConnector);
        return $this->successApiResponse();
    }

    private function saveExerciseResources(ExerciseRequest $request, Exercise $exercise): ?Resource {
        $resourceService = $this->getResourceService();
        $validated = $request->validated();
        $resource = null;
        if($request->hasFile('image')){
            $resource = $resourceService->saveEntityImage($request->file('image'), 'images/exercises', CoreModel::exerciseModelId,
                $exercise->getEntityId(), $exercise->getName(), $exercise->getSlug());
        } elseif(!empty($validated['public_resource_key'])){
            $resource = $resourceService->saveEntityPublicImage(CoreModel::exerciseModelId, $exercise->getEntityId(),
                $validated['public_resource_key'], $exercise->getName(), $exercise->getSlug());
        }

        $mediaKeyList = array_diff(array_unique($validated['public_resource_media_keys'] ?? []), [$validated['public_resource_key'] ?? null]);
        foreach ($mediaKeyList as $mediaKey) {
            $resourceService->attachPublicResource(CoreModel::exerciseModelId, $exercise->getEntityId(), $mediaKey);
        }
        return $resource;
    }

    private function canManageExerciseResources(?User $user, Exercise $exercise): bool {
        if(!$user){
            return false;
        }
        return $this->isAdminViewer($user)
            || ($exercise->getOwnerId() === $user->id && $user->hasPermit($this->getDbConnector(), Permit::createCatalogEntityPermitSlug));
    }

    private function manageExerciseResourcesOrFail(?User $user, Exercise $exercise): void {
        if(!$this->canManageExerciseResources($user, $exercise)){
            throw PublicException::forbiddenError('No puedes cambiar los recursos de este ejercicio');
        }
    }

    /**
     * Lo privado de otro usuario o lo que todavia no se aprobo responde como inexistente.
     */
    private function queryVisibleExerciseOrFail(Request $request, int $exerciseId): Exercise {
        $exercise = Exercise::queryByDbIdOrFail($this->getDbConnector(), $exerciseId);
        $viewer = $this->queryViewer($request);
        if(!$exercise->canBeSeenBy($viewer?->id, $this->isAdminViewer($viewer))){
            throw PublicException::notFoundError('No se encuentra ejercicio con id: '.$exerciseId);
        }
        return $exercise;
    }

    private function queryVisibleMuscleOrFail(Request $request, int $muscleId): Muscle {
        $muscle = Muscle::queryByDbIdOrFail($this->getDbConnector(), $muscleId);
        $viewer = $this->queryViewer($request);
        if(!$muscle->canBeSeenBy($viewer?->id, $this->isAdminViewer($viewer))){
            throw PublicException::notFoundError('No se encuentra musculo con id: '.$muscleId);
        }
        return $muscle;
    }
}
