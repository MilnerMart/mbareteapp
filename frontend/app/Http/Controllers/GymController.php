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

    /**
     * Gimnasios que gestiona el usuario mas los gimnasios donde es alumno. El base se muestra
     * solo si activa el deslizador. Con ?member={id} muestra el detalle de uno de esos gimnasios.
     */
    public function index(Request $request): View{
        $memberData = $this->buildMemberGymData($request);
        $data = $memberData + [
            'gyms' => $memberData['memberGym'] ? [] : $this->mergeMemberGyms(
                $this->apiClient->getGyms() ?? [],
                $memberData['visibleMemberGyms']
            ),
            'isAdmin' => AuthPermits::isAdmin(),
            'memberRoute' => 'gym.index',
        ];
        return $this->renderView('gyms.index', compact('data'));
    }

    /**
     * "Mis gimnasios" del alumno: igual que el entrenador, pero sin gimnasios propios.
     */
    public function memberIndex(Request $request): View{
        $memberData = $this->buildMemberGymData($request);
        $data = $memberData + [
            'gyms' => $memberData['memberGym'] ? [] : $this->mergeMemberGyms([], $memberData['visibleMemberGyms']),
            'isAdmin' => false,
            'memberRoute' => 'gym.member',
        ];
        return $this->renderView('gyms.member', compact('data'));
    }

    /**
     * Gimnasios donde el usuario es alumno. El deslizador (?base_gym=1, se recuerda en sesion)
     * decide si el gimnasio base va en la lista; los demas se muestran siempre.
     */
    private function buildMemberGymData(Request $request): array{
        if($request->has('base_gym')){
            $request->session()->put('gyms_show_base', $request->boolean('base_gym'));
        }
        $memberGyms = AuthPermits::canBelongToGym() ? ($this->apiClient->getMemberGyms() ?? []) : [];
        // el alumno lo ve de entrada; quien gestiona gimnasios lo suma a su lista si quiere
        $showBaseGym = (bool) session('gyms_show_base', !AuthPermits::canManageGyms());

        return [
            'baseGym' => collect($memberGyms)->firstWhere('isBase', true),
            'showBaseGym' => $showBaseGym,
            'visibleMemberGyms' => array_values(array_filter($memberGyms, fn($gym) => $showBaseGym || !($gym['isBase'] ?? false))),
            'memberGym' => collect($memberGyms)->firstWhere('id', (int) $request->query('member')),
        ];
    }

    /**
     * Los gimnasios donde es alumno van como uno mas de la lista; si ademas lo gestiona (ej. el admin
     * con el gimnasio base) no se duplica, solo se le agrega la membresia.
     */
    private function mergeMemberGyms(array $gyms, array $memberGyms): array{
        $gyms = array_map(fn($gym) => $gym + ['isManaged' => true, 'membership' => null], $gyms);
        foreach ($memberGyms as $memberGym) {
            $index = array_search($memberGym['id'], array_column($gyms, 'id'), true);
            if($index === false){
                $gyms[] = $memberGym + ['isManaged' => false, 'membership' => $memberGym];
            } else {
                $gyms[$index]['membership'] = $memberGym;
            }
        }
        return $gyms;
    }

    public function updateMemberVisibility(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $response = $this->apiClient->updateMemberGymVisibility($id, (bool) $validated['is_public']);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['is_public' => $this->apiErrorMessage($response, 'No pudimos guardar tu preferencia.')]);
        }

        return back()->with('gym_saved', $validated['is_public']
            ? 'Ahora apareces en la lista de alumnos.'
            : 'Ya no apareces en la lista de alumnos.');
    }

    public function create(): View{
        $data['gym'] = null;
        $data['isAdmin'] = AuthPermits::isAdmin();
        return $this->renderView('gyms.form', compact('data'));
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
        return $this->renderView('gyms.show', compact('data'));
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
        return $this->renderView('gyms.form', compact('data'));
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
