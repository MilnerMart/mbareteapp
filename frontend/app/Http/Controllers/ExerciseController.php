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
        return $this->renderView('exercises.index', compact('data'));
    }

    public function getExerciseGroup(int $muscleId){
        $exerciseGroup = $this->apiClient->getExerciseGroup($muscleId);
        $data['exerciseGroup'] = $exerciseGroup;
        return $this->renderView('exercises.group', compact('data'));
    }

    public function getExerciseResource(int $exerciseId){
        if(!session('auth_user')){
            return redirect()->route('user.login')
                ->with('login_notice', 'Registrate o inicia sesion para ver el ejercicio y sumarlo a tus rutinas.');
        }

        $resources = $this->apiClient->getResource($exerciseId);
        $data['exerciseResources'] = $resources;
        $data['exerciseId'] = $exerciseId;
        // solo las rutinas que el usuario puede modificar
        $data['editableRoutines'] = session('auth_user')
            ? array_values(array_filter($this->apiClient->getRoutines() ?? [], fn($routine) => $routine['canEdit']))
            : [];
        return $this->renderView('exercises.resources', compact('data'));
    }
    
}
