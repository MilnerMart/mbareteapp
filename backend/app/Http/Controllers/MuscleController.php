<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Http\Requests\MuscleRequest;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\Resource;
use App\PublicException;
use App\Services\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MuscleController extends Controller {
    public function index(): JsonResponse {
        $dbconnector = $this->getDbConnector();   
        $muscleList = Muscle::queryList($dbconnector);
        $model=[];
        foreach ($muscleList as $muscle) {
            /**  @var Muscle $muscle */
            $resource = Resource::queryByOwnerAndModelId($dbconnector, CoreModel::muscleModelId, $muscle->getEntityId());
            $muscleModel = $muscle->buildApiModel();
            $muscleModel['image_url'] = $resource?->getPublicUrl();
            $model[]= $muscleModel;
        }
        return $this->successApiResponse($model);
    }

    public function store(MuscleRequest $request): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $validated = $request->validated();
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::allocMuscle(
            $validated['name'],
            $validated['slug'] ?? Str::slug($validated['name']),
            $validated['description'],
            $validated['recommended_rest_days'],
        );
        $muscle->writeToDb($dbConnector);
        $resource = $this->saveMuscleImage($request, $muscle);

        $model = $muscle->buildApiModel();
        $model['image_url'] = $resource?->getPublicUrl();
        return $this->successApiResponse($model, 201);
    }

    public function show(string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::queryByDbIdOrFail($dbConnector, $id);
        $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::muscleModelId, $muscle->getEntityId());

        $model = $muscle->buildApiModel();
        $model['image_url'] = $resource?->getPublicUrl();
        return $this->successApiResponse($model, 200);
    }

    public function update(MuscleRequest $request, string $id): JsonResponse {   
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::queryByDbIdOrFail($dbConnector,$id);
        $validated = $request->validated();
        $dirty = false;
        if($muscle->getName() !== $validated['name']){
            $muscle->setName($validated['name']);
            $dirty = true;
        }
        if(!empty($validated['slug']) && $muscle->getSlug() !== $validated['slug']){
            $muscle->setSlug($validated['slug']);
            $dirty = true;
        }
        if($muscle->getRestDays() !== $validated['recommended_rest_days']){
            $muscle->setRestDays($validated['recommended_rest_days']);
            $dirty = true;
        }
        if($muscle->getDescription() !== $validated['description']){
            $muscle->setDescription($validated['description']);
            $dirty = true;
        }
        if($dirty){
            $muscle->writeToDb($dbConnector);
        }
        $this->saveMuscleImage($request, $muscle);

        $model = $muscle->buildApiModel();
        return $this->successApiResponse($model, 200);
    }

    public function destroy(Request $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::queryByDbIdOrFail($dbConnector,$id);

        $exerciseCount = count(Exercise::queryListByMuscleId($dbConnector, $muscle->getEntityId()));
        if($exerciseCount > 0){
            throw PublicException::validationError('No se puede eliminar '.$muscle->getName().' porque tiene '.$exerciseCount.' ejercicio(s). Elimina primero sus ejercicios.');
        }

        $muscle->setStatusId(EntityStatus::statusIdInactive);
        $muscle->writeToDb($dbConnector);
        return $this->successApiResponse(code: 200);
    }

    private function saveMuscleImage(MuscleRequest $request, Muscle $muscle): ?Resource {
        if(!$request->hasFile('image')){
            return null;
        }
        $service = new ResourceService($this->getDbConnector());
        return $service->saveEntityImage($request->file('image'), 'images/muscles', CoreModel::muscleModelId,
            $muscle->getEntityId(), $muscle->getName(), $muscle->getSlug());
    }
}
