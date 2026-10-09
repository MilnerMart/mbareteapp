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
        return $this->renderView('routines.index', compact('data'));
    }

    public function create(Request $request): View{
        $data['routine'] = null;
        // si se crea desde un ejercicio, se agrega a la rutina al guardarla
        $data['exerciseId'] = $request->integer('exercise_id') ?: null;
        return $this->renderView('routines.form', compact('data'));
    }

    public function store(Request $request): RedirectResponse{
        $exerciseId = $request->validate(['exercise_id' => ['nullable', 'integer']])['exercise_id'] ?? null;
        $response = $this->apiClient->createRoutine($this->validateRoutine($request));

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos crear la rutina.')])
                ->withInput();
        }

        $routineId = $response['data']['id'];

        if(!$exerciseId){
            return redirect()->route('routine.show', $routineId)
                ->with('routine_saved', 'Rutina creada. Agrega ejercicios desde Inicio.');
        }

        $exerciseResponse = $this->apiClient->addRoutineExercise($routineId, [
            'exercise_id' => $exerciseId,
            'sets' => null,
            'reps' => null,
        ]);

        if(!$this->isApiSuccess($exerciseResponse)){
            return redirect()->route('routine.show', $routineId)
                ->withErrors(['routine' => $this->apiErrorMessage($exerciseResponse, 'Rutina creada, pero no pudimos agregar el ejercicio.')]);
        }

        return redirect()->route('routine.show', $routineId)
            ->with('routine_saved', 'Rutina creada con el ejercicio agregado.');
    }

    public function show(int $id): RedirectResponse|View{
        $routine = $this->apiClient->getRoutine($id);
        if(!$routine){
            return redirect()->route('routine.index');
        }

        $data['routine'] = $routine;
        return $this->renderView('routines.show', compact('data'));
    }

    public function edit(int $id): RedirectResponse|View{
        $routine = $this->apiClient->getRoutine($id);
        if(!$routine || !$routine['canEdit']){
            return redirect()->route('routine.index');
        }

        $data['routine'] = $routine;
        return $this->renderView('routines.form', compact('data'));
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

    public function updateExercise(Request $request, int $id, int $exerciseId): RedirectResponse{
        $validated = $request->validate([
            'sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reps' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $response = $this->apiClient->updateRoutineExercise($id, $exerciseId, [
            'sets' => $validated['sets'] ?? null,
            'reps' => $validated['reps'] ?? null,
        ]);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos actualizar el ejercicio.')]);
        }

        return back()->with('routine_saved', 'Series y repeticiones actualizadas.');
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

        return back()->with(['gym_saved' => 'Rutina quitada.', 'routine_saved' => 'Usuario quitado de la rutina.']);
    }

    private function validateRoutine(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'integer', 'min:1', 'max:7'],
            'rest_minutes' => ['required', 'numeric', 'min:0', 'max:30'],
        ]);
    }
}
