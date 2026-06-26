<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExerciseRequest;
use App\Http\Requests\ResourceRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\ExerciseResourceModel;
use App\Models\Muscle;
use Exception;
use Log;

class ExerciseController extends Controller
{
    public function index()
    {
        $exerciseList = Exercise::all();
        $exerciseResource = ExerciseResource::make($exerciseList);
        return $this->successApiResponse($exerciseResource);
    }

    public function store(ExerciseRequest $request)
    {
        $name = $request->input('name');
        if(!$name){
            throw new Exception("El nombre es requerido para crear el recurso", 400);
        }
        $slug = $request->input('slug');
        if(!$slug){
            throw new Exception("El slug es requerido para crear el recurso", 400);
        }
        $muscleId = $request->input('muscle_id');
        $muscle = Muscle::find($muscleId);
        if(!$muscle){
            throw new Exception('No se verifica musculo con id: '.$muscleId, 400);
        }
        $restTime = $request->input('recommended_rest_time');
        if(!$restTime){
            throw new Exception("El tiempo de descanso requerido para crear el recurso", 400);
        }

        $description = $request->input('description');
        $imageUrl = $request->input('imageUrl');
        $videoUrl = $request->input('videoUrl');
        $gifUrl = $request->input('gifUrl');

        $exercise = Exercise::allocNew($name, $slug,$muscleId, $restTime, $description, $imageUrl, $videoUrl, $gifUrl);
        $exercise->save();

        $exerciseResource = ExerciseResource::make($exercise);
        return $this->successApiResponse($exerciseResource, 200);
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

        if($exercise->name !== ($name = $request->input('name'))){
            $exercise->setName($name);
            $dirty = true;
        }

        if($exercise->slug !== ($slug = $request->input('slug'))){
            $exercise->setSlug($slug);
            $dirty = true;
        }

        $muscleId = $request->input('muscleId');
        $muscle = Muscle::find($muscleId);
        if($muscle){
            throw new Exception("No se verifica musculo con id: ". $id, 400);
        }
        if($exercise->muscle_id !== $muscleId){
            $exercise->setMuscle($slug);
            $dirty = true;
        }

        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $id, 400);
        }
    }

    public function destroy(string $id)
    {
        $exercise = Exercise::find($id);

        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $id, 400);
        }

        $exercise->delete();
        $this->successApiResponse(code: 200);
    }

    public function getExerciseGroup(int $muscleId){
        $exerciseGroup = Exercise::all()->where('muscle_id', $muscleId);
        $exerciseResource = ExerciseResource::make($exerciseGroup);
        return $this->successApiResponse($exerciseResource);
    }

    public function addResource(ResourceRequest $request, int $exerciseId){
        $exercise = Exercise::find($exerciseId);
        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $exerciseId, 400);
        }
        
        $name = $request->input('name');
        if(!$name){
            throw new Exception("El nombre es requerido para crear el recurso", 400);
        }

        $kindId = $request->input('kind');
        if(!$kindId){
            throw new Exception("El id del tipo de archivo es requerido para crear el recurso", 400);
        }
        
        $kind = ExerciseResourceModel::kindMap($kindId);
        if(!$kind){
            throw new Exception("tipo de archivo no reconocido", 400);
        }

        $url = $request->input('url');
        if(!$url){
            throw new Exception("La url es requerida para crear el recurso", 400);
        }

        $statusId = $request->input('statusId');
        if(!$statusId){
            throw new Exception("El estado es requerido para crear el recurso", 400);
        }

        $resource = ExerciseResourceModel::allocNew($name, $kindId, $url, $exerciseId, $statusId);
        $resource->save();
        return $this->successApiResponse();
    }

    public function getResources(int $exerciseId){

        $exercise = Exercise::find($exerciseId);
        if(!$exercise){
            throw new Exception("No se verifica ejercicio con id: ". $exerciseId, 400);
        }

        $resources = ExerciseResourceModel::all()->where('exercise_id', $exerciseId)->toArray();
        if(empty($resources)){
            
        }
        $exerciseResources = ExerciseResource::make($resources);

        return $this->successApiResponse($exerciseResources);
    }
}
