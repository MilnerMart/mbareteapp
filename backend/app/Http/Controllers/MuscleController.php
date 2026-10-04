<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Http\Requests\MuscleRequest;
use App\Models\Muscle;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;

class MuscleController extends Controller {
    public function index(): JsonResponse {
        $dbconnector = $this->getDbConnector();   
        $muscleList = Muscle::queryList($dbconnector);
        $model=[];
        foreach ($muscleList as $muscle) {
            /**  @var Muscle $muscle */
            $resource = Resource::queryByOwnerAndModelId($dbconnector, CoreModel::muscleModelId, $muscle->getEntityId());
            $muscleModel = $muscle->buildApiModel();
            $muscleModel['image_url'] = $resource->getUrl();
            $model[]= $muscleModel;
        }
        return $this->successApiResponse($model);
    }

    public function store(MuscleRequest $request): JsonResponse {
        $validated = $request->validated();
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::allocMuscle(
            $validated['name'],
            $validated['slug'],
            $validated['description'],
            $validated['recommended_rest_days'],
        );
        $muscle->writeToDb($dbConnector);

        return $this->successApiResponse($muscle->buildApiModel(), 201);
    }

    public function show(string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::queryByDbIdOrFail($dbConnector, $id);

        return $this->successApiResponse($muscle->buildApiModel(), 200);
    }

    public function update(MuscleRequest $request, string $id): JsonResponse {   
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::queryByDbIdOrFail($dbConnector,$id);
        $validated = $request->validated();
        $dirty = false;
        if($muscle->getName() !== $validated['name']){
            $muscle->setName($validated['name']);
            $dirty = true;
        }
        if($muscle->getSlug() !== $validated['slug']){
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

        $model = $muscle->buildApiModel();
        return $this->successApiResponse($model, 200);
    }

    public function destroy(string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $muscle = Muscle::queryByDbIdOrFail($dbConnector,$id);

        $muscle->setStatusId(EntityStatus::statusIdDeleted);
        $muscle->writeToDb($dbConnector);
        return $this->successApiResponse(code: 200);
    }
    
}
