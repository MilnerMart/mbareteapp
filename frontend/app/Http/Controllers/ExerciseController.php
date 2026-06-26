<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(){
        $exerciseList = $this->apiClient->getExercises();
        $data['exercises'] = $exerciseList;
        return view('exercises.index', compact('data'));
    }

    public function getExerciseGroup(int $muscleId){
        $exerciseGroup = $this->apiClient->getExerciseGroup($muscleId);
        $data['exerciseGroup'] = $exerciseGroup;
        return view('exercises.group', compact('data'));
    }

    public function getExerciseResource(int $exerciseId){
        $resources = $this->apiClient->getExerciseResources($exerciseId);
        $data['exerciseResources'] = $resources;
        return view('exercises.resources', compact('data'));
    }
    
}
