<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\AuthPermits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MuscleController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(): View{
        $muscleList = $this->apiClient->getMuscles();
        $data['muscles'] = $muscleList;
        $data['isAdmin'] = AuthPermits::isAdmin();
        return $this->renderView('muscles.index', compact('data'));
    }

    public function create(): View{
        $data['muscle'] = null;
        return $this->renderView('muscles.form', compact('data'));
    }

    public function edit(int $id): RedirectResponse|View{
        $muscle = $this->apiClient->getMuscle($id);
        if(!$muscle){
            return redirect()->route('muscle.index');
        }

        $data['muscle'] = $muscle;
        return $this->renderView('muscles.form', compact('data'));
    }

    public function update(Request $request, int $id): RedirectResponse{
        $validated = $request->validate($this->muscleRules(false));

        $image = $validated['image'] ?? null;
        unset($validated['image']);
        $response = $this->apiClient->updateMuscle($id, $validated, $image);

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos actualizar el musculo.')])
                ->withInput();
        }

        return redirect()->route('muscle.index')->with('catalog_saved', 'Musculo actualizado.');
    }

    public function store(Request $request): RedirectResponse{
        $validated = $request->validate($this->muscleRules(true));

        $image = $validated['image'];
        unset($validated['image']);
        $response = $this->apiClient->createMuscle($validated, $image);

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos crear el musculo.')])
                ->withInput();
        }

        return redirect()->route('muscle.index')->with('catalog_saved', 'Musculo creado.');
    }

    public function destroy(int $id): RedirectResponse{
        $response = $this->apiClient->deleteMuscle($id);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['muscle' => $this->apiErrorMessage($response, 'No pudimos eliminar el musculo.')]);
        }

        return redirect()->route('muscle.index')->with('catalog_saved', 'Musculo eliminado.');
    }

    // la imagen es obligatoria al crear, al editar es opcional
    private function muscleRules(bool $imageRequired): array{
        return [
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'recommended_rest_days' => ['required', 'integer', 'min:1', 'max:14'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ];
    }
}
