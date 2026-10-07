<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\AuthPermits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GymController extends Controller
{
    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(): View{
        $data['gyms'] = $this->apiClient->getGyms() ?? [];
        $data['isAdmin'] = AuthPermits::isAdmin();
        return view('gyms.index', compact('data'));
    }

    public function create(): View{
        $data['gym'] = null;
        $data['isAdmin'] = AuthPermits::isAdmin();
        return view('gyms.form', compact('data'));
    }

    public function store(Request $request): RedirectResponse{
        $response = $this->apiClient->createGym($this->validateGym($request));

        if(!$this->isSuccess($response)){
            return back()
                ->withErrors(['name' => $this->errorMessage($response, 'No pudimos crear el gimnasio.')])
                ->withInput();
        }

        return redirect()->route('gym.index')->with('gym_saved', 'Gimnasio creado.');
    }

    public function edit(int $id): RedirectResponse|View{
        $gym = $this->apiClient->getGym($id);
        if(!$gym){
            return redirect()->route('gym.index');
        }

        $data['gym'] = $gym;
        $data['isAdmin'] = AuthPermits::isAdmin();
        return view('gyms.form', compact('data'));
    }

    public function update(Request $request, int $id): RedirectResponse{
        $response = $this->apiClient->updateGym($id, $this->validateGym($request));

        if(!$this->isSuccess($response)){
            return back()
                ->withErrors(['name' => $this->errorMessage($response, 'No pudimos actualizar el gimnasio.')])
                ->withInput();
        }

        return redirect()->route('gym.index')->with('gym_saved', 'Gimnasio actualizado.');
    }

    private function validateGym(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:5', 'max:200'],
            'slug' => ['required', 'string', 'min:5', 'max:50', 'alpha_dash'],
        ];
        // solo el admin puede asignar el gimnasio a otro usuario
        if(AuthPermits::isAdmin()){
            $rules['owner_id'] = ['nullable', 'integer'];
        }

        return $request->validate($rules);
    }

    private function errorMessage(?array $response, string $fallback): string
    {
        if(isset($response['error']['message'])){
            return $response['error']['message'];
        }

        if(isset($response['message'])){
            return $response['message'];
        }

        $firstError = $response['errors'] ?? null;

        if(is_array($firstError)){
            $fieldErrors = reset($firstError);
            if(is_array($fieldErrors)){
                return $fieldErrors[0] ?? $fallback;
            }
        }

        return $fallback;
    }

    private function isSuccess(?array $response): bool
    {
        return (bool) ($response['success'] ?? false);
    }
}
