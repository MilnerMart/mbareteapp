<?php

namespace App\Http\Controllers;

use App\Http\Requests\MuscleRequest;
use App\Http\Resources\MuscleResource;
use App\Models\Muscle;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MuscleController extends Controller
{
    public function index(): JsonResponse
    {
        $musclesCollection = Muscle::all();
        $muscleResource = MuscleResource::collection($musclesCollection);
        return $this->successApiResponse($muscleResource);
    }

    public function store(MuscleRequest $request)
    {
        $name = $request->input('name');
        if(!$name){
            throw new Exception("El nombre es requerido para crear el recurso", 400);
        }

        $slug = $request->input('slug');
        if(!$slug){
            throw new Exception("El identificador es requerido para crear el recurso", 400);
        }

        $description = $request->input('description');
        if(!$description){
            throw new Exception("la imagen es requerido para crear el recurso", 400);
        }

        $restDays = $request->input('recommended_rest_days');
        if(!$restDays){
            throw new Exception("días de descanso es requerido para crear el recurso", 400);
        }

        $imageUrl = $request->input('image_url');
        if(!$imageUrl){
            throw new Exception("la imagen es requerido para crear el recurso", 400);
        }

        $muscle = Muscle::allocMuscle($name, $slug, $description,$restDays, $imageUrl);
        $muscle->save();

        $muscleResource = MuscleResource::make($muscle);
        $this->successApiResponse($muscleResource, 200);
    }

    public function show(string $id)
    {
        $muscle = Muscle::find($id);

        if(!$muscle){
            throw new Exception("No se verifica musculo con id: ". $id, 400);
        }

        $muscleResource = MuscleResource::make($muscle);

        return $this->successApiResponse($muscleResource, 200);
    }

    public function update(Request $request, string $id)
    {   
        
        $muscle = Muscle::findOrFail($id);
        $dirty = false;
        if($muscle->name !== ($name = $request->input('name'))){
            $muscle->setName($name);
            $dirty = true;
        }

        if($muscle->slug !== ($slug = $request->input('slug'))){
            $muscle->setSlug($slug);
            $dirty = true;
        }

        if($muscle->recommended_rest_days !== ($restDays = $request->input('recommended_rest_days'))){
            $muscle->setRestDays($restDays);
            $dirty = true;
        }

        if($muscle->image_url !== ($imageUrl = $request->input('image_url'))){
            $muscle->setImg($imageUrl);
            $dirty = true;
        }

        if($muscle->description !== ($description = $request->input('description'))){
            $muscle->setDescription($description);
            $dirty = true;
        }
        
        if($dirty){
            $muscle->save();
        }

        $muscleResource = MuscleResource::make($muscle);

        $this->successApiResponse($muscleResource, 200);
    }

    public function destroy(string $id)
    {
        $muscle = Muscle::find($id);

        if(!$muscle){
            throw new Exception("No se verifica musculo con id: ". $id, 400);
        }

        $muscle->delete();
        $this->successApiResponse(code: 200);
    }
}
