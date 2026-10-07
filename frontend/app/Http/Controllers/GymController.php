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

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos crear el gimnasio.')])
                ->withInput();
        }

        return redirect()->route('gym.index')->with('gym_saved', 'Gimnasio creado.');
    }

    public function show(int $id): RedirectResponse|View{
        $gym = $this->apiClient->getGym($id);
        if(!$gym){
            return redirect()->route('gym.index');
        }

        $data['gym'] = $gym;
        $data['students'] = $this->apiClient->getGymUsers($id) ?? [];
        $data['isAdmin'] = AuthPermits::isAdmin();
        $data['canAssignRoutines'] = AuthPermits::canAssignRoutines();
        // el entrenador asigna sus propias rutinas, el admin cualquiera
        $userId = (int) session('auth_user.id');
        $data['assignableRoutines'] = $data['canAssignRoutines']
            ? array_values(array_filter(
                $this->apiClient->getRoutines() ?? [],
                fn($routine) => $data['isAdmin'] || $routine['ownerId'] === $userId
            ))
            : [];
        return view('gyms.show', compact('data'));
    }

    public function updateImage(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'gym_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $response = $this->apiClient->updateGymImage($id, $validated['gym_image']);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors([
                'gym_image' => $this->apiErrorMessage($response, 'No pudimos actualizar la imagen del gimnasio.'),
            ]);
        }

        return back()->with('gym_saved', 'Imagen del gimnasio actualizada.');
    }

    public function removeUser(int $id, int $userId): RedirectResponse{
        $response = $this->apiClient->removeGymUser($id, $userId);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors([
                'student' => $this->apiErrorMessage($response, 'No pudimos quitar al alumno.'),
            ]);
        }

        return back()->with('gym_saved', 'Alumno quitado del gimnasio.');
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

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['name' => $this->apiErrorMessage($response, 'No pudimos actualizar el gimnasio.')])
                ->withInput();
        }

        return redirect()->route('gym.show', $id)->with('gym_saved', 'Gimnasio actualizado.');
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
}
