<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoutineController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(): View{
        $userId = (int) session('auth_user.id');
        $routineList = $this->apiClient->getRoutines() ?? [];

        // propias, asignadas por otro, y el resto (solo las ve el admin)
        $data['ownRoutines'] = array_filter($routineList, fn($routine) => $routine['ownerId'] === $userId);
        $data['assignedRoutines'] = array_filter($routineList, fn($routine) => $routine['ownerId'] !== $userId && $routine['isAssigned']);
        $data['otherRoutines'] = array_filter($routineList, fn($routine) => $routine['ownerId'] !== $userId && !$routine['isAssigned']);
        return view('routines.index', compact('data'));
    }

    public function create(): View{
        $data['routine'] = null;
        return view('routines.form', compact('data'));
    }

    public function store(Request $request): RedirectResponse{
        $response = $this->apiClient->createRoutine($this->validateRoutine($request));

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos crear la rutina.')])
                ->withInput();
        }

        return redirect()->route('routine.show', $response['data']['id'])
            ->with('routine_saved', 'Rutina creada. Agrega ejercicios desde Inicio.');
    }

    public function show(int $id): RedirectResponse|View{
        $routine = $this->apiClient->getRoutine($id);
        if(!$routine){
            return redirect()->route('routine.index');
        }

        $data['routine'] = $routine;
        return view('routines.show', compact('data'));
    }

    public function edit(int $id): RedirectResponse|View{
        $routine = $this->apiClient->getRoutine($id);
        if(!$routine || !$routine['canEdit']){
            return redirect()->route('routine.index');
        }

        $data['routine'] = $routine;
        return view('routines.form', compact('data'));
    }

    public function update(Request $request, int $id): RedirectResponse{
        $response = $this->apiClient->updateRoutine($id, $this->validateRoutine($request));

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos actualizar la rutina.')])
                ->withInput();
        }

        return redirect()->route('routine.show', $id)->with('routine_saved', 'Rutina actualizada.');
    }

    public function destroy(int $id): RedirectResponse{
        $response = $this->apiClient->deleteRoutine($id);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos eliminar la rutina.')]);
        }

        return redirect()->route('routine.index')->with('routine_saved', 'Rutina eliminada.');
    }

    public function addExercise(Request $request): RedirectResponse{
        $validated = $request->validate([
            'routine_id' => ['required', 'integer'],
            'exercise_id' => ['required', 'integer'],
            'sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reps' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $response = $this->apiClient->addRoutineExercise($validated['routine_id'], [
            'exercise_id' => $validated['exercise_id'],
            'sets' => $validated['sets'] ?? null,
            'reps' => $validated['reps'] ?? null,
        ]);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos agregar el ejercicio.')]);
        }

        return back()->with('routine_saved', 'Ejercicio agregado a la rutina.');
    }

    public function removeExercise(int $id, int $exerciseId): RedirectResponse{
        $response = $this->apiClient->removeRoutineExercise($id, $exerciseId);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos quitar el ejercicio.')]);
        }

        return back()->with('routine_saved', 'Ejercicio quitado de la rutina.');
    }

    public function assign(Request $request): RedirectResponse{
        $validated = $request->validate([
            'routine_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
        ]);

        $response = $this->apiClient->assignRoutine($validated['routine_id'], $validated['user_id']);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos asignar la rutina.')]);
        }

        return back()->with('gym_saved', 'Rutina asignada.');
    }

    public function unassign(int $id, int $userId): RedirectResponse{
        $response = $this->apiClient->unassignRoutine($id, $userId);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos quitar la rutina.')]);
        }

        return back()->with('gym_saved', 'Rutina quitada.');
    }

    private function validateRoutine(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'integer', 'min:1', 'max:7'],
            'rest_time' => ['required', 'integer', 'min:0', 'max:600'],
        ]);
    }
}
