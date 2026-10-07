<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\AuthPermits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $muscle = $this->apiClient->getMuscle($muscleId);
        if(!$muscle){
            return redirect()->route('muscle.index');
        }

        $data['muscle'] = $muscle;
        $data['exerciseGroup'] = $this->apiClient->getExerciseGroup($muscleId);
        $data['isAdmin'] = AuthPermits::isAdmin();
        return $this->renderView('exercises.group', compact('data'));
    }

    public function getExerciseResource(int $exerciseId){
        if(!session('auth_user')){
            return redirect()->route('user.login')
                ->with('login_notice', 'Registrate o inicia sesion para ver el ejercicio y sumarlo a tus rutinas.');
        }

        $exercise = $this->apiClient->getExercise($exerciseId);
        if(!$exercise){
            return redirect()->route('muscle.index');
        }

        $data['exercise'] = $exercise;
        $data['exerciseResources'] = $this->apiClient->getExerciseResources($exerciseId);
        $data['exerciseId'] = $exerciseId;
        // solo las rutinas que el usuario puede modificar
        $data['editableRoutines'] = array_values(array_filter($this->apiClient->getRoutines() ?? [], fn($routine) => $routine['canEdit']));
        return $this->renderView('exercises.resources', compact('data'));
    }

    public function create(int $muscleId): RedirectResponse|View{
        $muscle = $this->apiClient->getMuscle($muscleId);
        if(!$muscle){
            return redirect()->route('muscle.index');
        }

        $data['muscle'] = $muscle;
        return $this->renderView('exercises.form', compact('data'));
    }

    public function store(Request $request): RedirectResponse{
        $validated = $request->validate([
            'muscle_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'recommended_rest_time' => ['required', 'integer', 'min:1', 'max:600'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $image = $validated['image'];
        unset($validated['image']);
        $response = $this->apiClient->createExercise($validated, $image);

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos crear el ejercicio.')])
                ->withInput();
        }

        return redirect()->route('exercise.group', $validated['muscle_id'])->with('catalog_saved', 'Ejercicio creado.');
    }
}
