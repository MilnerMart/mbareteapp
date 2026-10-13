<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\RoutineVolume;
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

        // en actividades (cardio, atletismo...) no se muestra el descanso entre series
        $routine['exercises'] = array_map(function(array $exercise){
            $isActivity = ($exercise['muscleKind'] ?? null) === 'activity';
            return $exercise + [
                'volume' => RoutineVolume::format($exercise),
                'detailVolume' => RoutineVolume::formatDetail($exercise, $isActivity),
            ];
        }, $routine['exercises'] ?? []);
        $data['routine'] = $routine;
        return $this->renderView('routines.show', compact('data'));
    }

    public function edit(int $id): RedirectResponse|View{
        $routine = $this->apiClient->getRoutine($id);
        if(!$routine || !$routine['canEdit']){
            return redirect()->route('routine.index');
        }

        // se edita en el navegador con la misma vista de la rutina: cada dato con su lapiz, y se guarda todo junto
        $data['routine'] = $routine;
        $data['fields'] = [
            'name' => old('name', $routine['name']),
            'description' => old('description', $routine['description'] ?? ''),
            'frequency' => old('frequency', $routine['frequency']),
            'rest_minutes' => old('rest_minutes', $routine['restMinutes']),
        ];
        $data['exercises'] = array_map(fn(array $exercise) => [
            'id' => $exercise['id'],
            'name' => $exercise['name'],
            'volume' => RoutineVolume::format($exercise),
            'notes' => $exercise['notes'] ?? null,
            'volumeFields' => RoutineVolume::buildFields($exercise, ($exercise['muscleKind'] ?? null) === 'activity', 'exercises['.$exercise['id'].']'),
        ], $routine['exercises'] ?? []);
        return $this->renderView('routines.edit', compact('data'));
    }

    public function update(Request $request, int $id): RedirectResponse{
        $params = $this->validateRoutine($request);
        if($request->boolean('sync_exercises')){
            $params['exercises'] = $this->validateRoutineExercises($request);
        }
        $response = $this->apiClient->updateRoutine($id, $params);

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
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $response = $this->apiClient->addRoutineExercise($validated['routine_id'], [
            'exercise_id' => $validated['exercise_id'],
            'sets' => $validated['sets'] ?? null,
            'reps' => $validated['reps'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['routine' => $this->apiErrorMessage($response, 'No pudimos agregar el ejercicio.')]);
        }

        return back()->with('routine_saved', 'Ejercicio agregado a la rutina.');
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

    // los campos llegan como exercises[id][...] en el orden en que quedaron en pantalla
    private function validateRoutineExercises(Request $request): array
    {
        $validated = $request->validate([
            'exercises' => ['nullable', 'array', 'max:100'],
            'exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'exercises.*.reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            'exercises.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'exercises.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        // validated() arma el array siguiendo el orden de las reglas, no el del formulario: el orden sale del input
        $exerciseList = [];
        foreach (array_keys((array)$request->input('exercises', [])) as $exerciseId) {
            $values = $validated['exercises'][$exerciseId] ?? [];
            $exerciseList[] = [
                'exercise_id' => (int)$exerciseId,
                'sets' => $values['sets'] ?? null,
                'reps' => $values['reps'] ?? null,
                'duration_minutes' => $values['duration_minutes'] ?? null,
                'notes' => $values['notes'] ?? null,
            ];
        }
        return $exerciseList;
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
