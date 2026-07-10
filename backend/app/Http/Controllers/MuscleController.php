<?php

namespace App\Http\Controllers;

use App\Http\Requests\MuscleRequest;
use App\Http\Resources\MuscleResource;
use App\Models\Muscle;
use Exception;
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
        $validated = $request->validated();

        $muscle = Muscle::allocMuscle(
            $validated['name'],
            $validated['slug'],
            $validated['description'],
            $validated['recommended_rest_days'],
            $validated['image_url']
        );
        $muscle->save();

        return $this->successApiResponse(MuscleResource::make($muscle), 201);
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

    public function update(MuscleRequest $request, string $id)
    {   
        
        $muscle = Muscle::findOrFail($id);
        $muscle->fill($request->validated());
        $muscle->save();

        $muscleResource = MuscleResource::make($muscle);

        return $this->successApiResponse($muscleResource, 200);
    }

    public function destroy(string $id)
    {
        $muscle = Muscle::find($id);

        if(!$muscle){
            throw new Exception("No se verifica musculo con id: ". $id, 400);
        }

        $muscle->delete();
        return $this->successApiResponse(code: 200);
    }
}
