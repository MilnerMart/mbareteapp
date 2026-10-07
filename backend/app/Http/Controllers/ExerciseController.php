<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Http\Requests\ExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\Resource;
use App\Services\ResourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExerciseController extends Controller
{
    public function index(): JsonResponse {
        return $this->successApiResponse(ExerciseResource::collection(Exercise::all()));
    }

    public function store(ExerciseRequest $request): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $validated = $request->validated();
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::allocNew(
            $validated['name'],
            $validated['slug'] ?? Str::slug($validated['name']),
            $validated['muscle_id'],
            $validated['recommended_rest_time'],
            $validated['description'],
        );
        $exercise->writeToDb($dbConnector);

        $service = new ResourceService($dbConnector);
        $resource = $service->saveEntityImage($request->file('image'), 'images/exercises', CoreModel::exerciseModelId,
            $exercise->getEntityId(), $exercise->getName(), $exercise->getSlug());

        $model = $exercise->buildApiModel();
        $model['image_url'] = $resource->getPublicUrl();
        return $this->successApiResponse($model, 201);
    }

    public function show(string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);
        $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());

        $model = $exercise->buildApiModel();
        $model['image_url'] = $resource?->getPublicUrl();
        return $this->successApiResponse($model, 200);
    }

    public function update(ExerciseRequest $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);

        $exercise->fill($request->validated());
        $exercise->save();

        return $this->successApiResponse(ExerciseResource::make($exercise));
    }

    public function destroy(Request $request, string $id): JsonResponse {
        $this->seeAllPermitOrFail($request->user());
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);

        $exercise->setStatusId(EntityStatus::statusIdDeleted);
        $exercise->writeToDb($dbConnector);
        return $this->successApiResponse(code: 200);
    }

    public function getExerciseGroup(int $muscleId): JsonResponse{
        $dbConnector = $this->getDbConnector();
        $exerciseGroup = Exercise::queryListByMuscleId($dbConnector, $muscleId);
        $model=[];
        foreach ($exerciseGroup as $exercise) {
            /**  @var Exercise $exercise */
            $resource = Resource::queryByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId());
            $exerciseModel = $exercise->buildApiModel();
            $exerciseModel['image'] = $resource?->getPublicUrl();
            $model[]= $exerciseModel;
        }
        return $this->successApiResponse($model);
    }

    public function getResources(int $exerciseId): JsonResponse{

        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$exerciseId);

        $model = [];
        foreach (Resource::queryListByOwnerAndModelId($dbConnector, CoreModel::exerciseModelId, $exercise->getEntityId()) as $resource) {
            /**  @var Resource $resource */
            $resourceModel = $resource->buildApiModel();
            $resourceModel['url'] = $resource->getPublicUrl();
            $model[] = $resourceModel;
        }
        return $this->successApiResponse($model);
    }
}
