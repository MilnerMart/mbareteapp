<?php

namespace App\Http\Controllers;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Http\Requests\ExerciseRequest;
use App\Http\Requests\ResourceRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\Resource;
use App\PublicException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExerciseController extends Controller
{
    public function index(): JsonResponse {
        return $this->successApiResponse(ExerciseResource::collection(Exercise::all()));
    }

    public function store(ExerciseRequest $request): JsonResponse {
        $validated = $request->validated();
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::allocNew(
            $validated['name'],
            $validated['slug'],
            $validated['muscle_id'],
            $validated['recommended_rest_time'],
            $validated['description'],
        );
        $exercise->writeToDb($dbConnector);

        return $this->successApiResponse(ExerciseResource::make($exercise), 201);
    }

    public function show(string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);

        $exerciseResource = ExerciseResource::make($exercise);

        return $this->successApiResponse($exerciseResource, 200);
    }

    public function update(ExerciseRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$id);

        $exercise->fill($request->validated());
        $exercise->save();

        return $this->successApiResponse(ExerciseResource::make($exercise));
    }

    public function destroy(string $id): JsonResponse {
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
            $exerciseModel['image'] = $resource->getUrl();
            $model[]= $exerciseModel;
        }
        return $this->successApiResponse($model);
    }

    public function addResource(ResourceRequest $request, int $exerciseId): JsonResponse{
        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$exerciseId);

        
        $validated = $request->validated();
        $kindId = $validated['kind'];
        
        $kind = Resource::kindMap($kindId);
        if(!$kind){
            throw PublicException::validationError("tipo de archivo no reconocido");
        }

        $resource = Resource::allocNew(
            $validated['name'],
            $kindId,
            $validated['url'],
            $exerciseId,
            $validated['status']
        );
        $resource->save();

        return $this->successApiResponse(ExerciseResource::make($resource), 201);
    }

    public function getResources(int $exerciseId): JsonResponse{

        $dbConnector = $this->getDbConnector();
        $exercise = Exercise::queryByDbIdOrFail($dbConnector,$exerciseId);

        $exerciseResources = ExerciseResource::collection(
            Resource::where('exercise_id', $exerciseId)->get()
        );

        return $this->successApiResponse($exerciseResources);
    }
}
