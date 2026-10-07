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
        return $this->renderView('muscles.form');
    }

    public function store(Request $request): RedirectResponse{
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'recommended_rest_days' => ['required', 'integer', 'min:1', 'max:14'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

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
}
