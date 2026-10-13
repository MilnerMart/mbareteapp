<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\AuthPermits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MuscleController extends Controller
{
    private const kindMuscle = 'muscle', kindActivity = 'activity';

    private const kindLabelMap = [
        self::kindMuscle => 'Musculo',
        self::kindActivity => 'Actividad',
    ];

    private const savedMessageMap = [
        self::kindMuscle => ['created' => 'Musculo creado.', 'updated' => 'Musculo actualizado.'],
        self::kindActivity => ['created' => 'Actividad creada.', 'updated' => 'Actividad actualizada.'],
    ];

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(): View{
        $groupList = collect($this->apiClient->getMuscles() ?? []);
        $data['muscles'] = $groupList->where('kind', '!=', self::kindActivity)->values()->all();
        $data['activities'] = $groupList->where('kind', self::kindActivity)->values()->all();
        $data['isAdmin'] = AuthPermits::isAdmin();
        return $this->renderView('muscles.index', compact('data'));
    }

    public function create(Request $request): View{
        $data['muscle'] = null;
        $data['kind'] = $request->query('kind') === self::kindActivity ? self::kindActivity : self::kindMuscle;
        $data['kindLabels'] = self::kindLabelMap;
        $data['isAdmin'] = AuthPermits::isAdmin();
        return $this->renderView('muscles.form', compact('data'));
    }

    public function edit(int $id): RedirectResponse|View{
        $muscle = $this->apiClient->getMuscle($id);
        if(!$muscle){
            return redirect()->route('muscle.index');
        }

        $data['muscle'] = $muscle;
        $data['kind'] = $muscle['kind'] ?? self::kindMuscle;
        $data['kindLabels'] = self::kindLabelMap;
        $data['isAdmin'] = AuthPermits::isAdmin();
        return $this->renderView('muscles.form', compact('data'));
    }

    public function update(Request $request, int $id): RedirectResponse{
        $validated = $request->validate($this->muscleRules(false));

        $image = $validated['image'] ?? null;
        unset($validated['image']);
        $response = $this->apiClient->updateMuscle($id, $validated, $image);

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos guardar los cambios.')])
                ->withInput();
        }

        return redirect()->route('muscle.index')->with('catalog_saved', self::savedMessageMap[$validated['kind']]['updated']);
    }

    public function store(Request $request): RedirectResponse{
        $validated = $request->validate($this->muscleRules(true));

        $image = $validated['image'];
        unset($validated['image']);
        $response = $this->apiClient->createMuscle($validated, $image);

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos crearlo.')])
                ->withInput();
        }

        return redirect()->route('muscle.index')->with('catalog_saved', $this->createdMessage($response, self::savedMessageMap[$validated['kind']]['created']));
    }

    public function destroy(int $id): RedirectResponse{
        $response = $this->apiClient->deleteMuscle($id);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['muscle' => $this->apiErrorMessage($response, 'No pudimos eliminarlo.')]);
        }

        return redirect()->route('muscle.index')->with('catalog_saved', 'Eliminado.');
    }

    // la imagen es obligatoria al crear, al editar es opcional
    private function muscleRules(bool $imageRequired): array{
        return [
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'recommended_rest_days' => ['required', 'integer', 'min:1', 'max:14'],
            'kind' => ['required', 'in:'.self::kindMuscle.','.self::kindActivity],
            'is_public' => ['required', 'boolean'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ];
    }
}
