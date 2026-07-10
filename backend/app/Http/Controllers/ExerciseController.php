<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExerciseRequest;
use App\Http\Requests\ResourceRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\ExerciseResourceModel;
use Exception;

class ExerciseController extends Controller
{
    public function index()
    {
        return $this->successApiResponse(ExerciseResource::collection(Exercise::all()));
    }

    public function store(ExerciseRequest $request)
    {
        $validated = $request->validated();

        $exercise = Exercise::allocNew(
            $validated['name'],
            $validated['slug'],
            $validated['muscle_id'],
            $validated['recommended_rest_time'],
            $validated['description'],
            $validated['image'] ?? null,
            $validated['video'] ?? null,
            $validated['gif'] ?? null
        );
        $exercise->save();

        return $this->successApiResponse(ExerciseResource::make($exercise), 201);
    }

    public function show(string $id)
    {
        $exercise = Exercise::find($id);

        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $id, 400);
        }

        $exerciseResource = ExerciseResource::make($exercise);

        return $this->successApiResponse($exerciseResource, 200);
    }

    public function update(ExerciseRequest $request, string $id)
    {
        $exercise = Exercise::find($id);

        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $id, 404);
        }

        $exercise->fill($request->validated());
        $exercise->save();

        return $this->successApiResponse(ExerciseResource::make($exercise));
    }

    public function destroy(string $id)
    {
        $exercise = Exercise::find($id);

        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $id, 400);
        }

        $exercise->delete();
        return $this->successApiResponse(code: 200);
    }

    public function getExerciseGroup(int $muscleId){
        $exerciseGroup = Exercise::where('muscle_id', $muscleId)->get();
        $exerciseResource = ExerciseResource::collection($exerciseGroup);
        return $this->successApiResponse($exerciseResource);
    }

    public function addResource(ResourceRequest $request, int $exerciseId){
        $exercise = Exercise::find($exerciseId);
        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $exerciseId, 400);
        }
        
        $validated = $request->validated();
        $kindId = $validated['kind'];
        
        $kind = ExerciseResourceModel::kindMap($kindId);
        if(!$kind){
            throw new Exception("tipo de archivo no reconocido", 400);
        }

        $resource = ExerciseResourceModel::allocNew(
            $validated['name'],
            $kindId,
            $validated['url'],
            $exerciseId,
            $validated['status']
        );
        $resource->save();

        return $this->successApiResponse(ExerciseResource::make($resource), 201);
    }

    public function getResources(int $exerciseId){

        $exercise = Exercise::find($exerciseId);
        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $exerciseId, 400);
        }

        $exerciseResources = ExerciseResource::collection(
            ExerciseResourceModel::where('exercise_id', $exerciseId)->get()
        );

        return $this->successApiResponse($exerciseResources);
    }
}
