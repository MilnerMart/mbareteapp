<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\AuthPermits;
use App\Support\NotificationInbox;
use App\Support\RankingLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GymController extends Controller
{
    // cupo de la fase beta (Gym::maxGymsPerTrainer en el backend): dos gimnasios para separar turnos
    private const maxGymsPerTrainer = 2;

    private const gymLimitMessage = 'Cada entrenador puede tener hasta '.self::maxGymsPerTrainer.' gimnasios.';

    private const seasonDateMessages = [
        'starts_on.required' => 'Elige cuando empieza la temporada.',
        'ends_on.required' => 'Elige cuando termina la temporada.',
        'ends_on.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
    ];

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
        $managedGyms = $this->apiClient->getGyms() ?? [];
        $data = $memberData + [
            'gyms' => $memberData['memberGym'] ? [] : $this->mergeMemberGyms($managedGyms, $memberData['visibleMemberGyms']),
            'isAdmin' => AuthPermits::isAdmin(),
            'memberRoute' => 'gym.index',
            'canCreateGym' => $this->canCreateGym($managedGyms),
        ];
        // en el gimnasio base el entrenador es un alumno mas: ve el ranking y compite si se muestra
        $data['ranking'] = $this->buildMemberRanking($request, $memberData['memberGym'], 'gym.index');
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
        $data['pendingJoinList'] = array_values(array_map(fn(array $ticket) => [
            'number' => $ticket['number'],
            'gymName' => $ticket['refs']['gym']['name'] ?? 'el gimnasio',
        ], array_filter($this->apiClient->getMyTickets() ?? [],
            fn(array $ticket) => ($ticket['type'] ?? null) === 'gym-join' && ($ticket['state'] ?? null) === 'pending')));
        // solo quien esta unicamente en el gimnasio base y no espera respuesta de otro puede pedir unirse
        $data['canJoinGym'] = !$memberData['memberGym'] && !$memberData['hasOwnGym'] && !$data['pendingJoinList'];
        $data['ranking'] = $this->buildMemberRanking($request, $memberData['memberGym'], 'gym.member');
        $data['isJoinFormOpen'] = (bool)session('errors')?->has('gym_code');
        return $this->renderView('gyms.member', compact('data'));
    }

    public function requestJoin(Request $request): RedirectResponse{
        $validated = $request->validate([
            'gym_code' => ['required', 'string', 'max:50'],
        ], [
            'gym_code.required' => 'Ingresa el codigo del gimnasio.',
        ]);

        $response = $this->apiClient->requestGymJoin($validated['gym_code']);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors([
                'gym_code' => $this->apiErrorMessage($response, 'No pudimos enviar tu solicitud.'),
            ])->withInput();
        }

        $gymName = $response['data']['refs']['gym']['name'] ?? 'el gimnasio';
        return back()->with('gym_saved', 'Enviamos tu solicitud '.$response['data']['number'].' para unirte a '.$gymName
            .'. Te avisaremos cuando el entrenador la acepte.');
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
            'hasOwnGym' => collect($memberGyms)->contains(fn($gym) => !($gym['isBase'] ?? false)),
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

    public function create(): RedirectResponse|View{
        if(!$this->canCreateGym($this->apiClient->getGyms() ?? [])){
            return redirect()->route('gym.index')->withErrors(['name' => self::gymLimitMessage]);
        }
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

    // el backend tambien lo valida
    private function canCreateGym(array $managedGyms): bool{
        $userId = (int) session('auth_user.id');
        return AuthPermits::isAdmin()
            || collect($managedGyms)->filter(fn(array $gym) => (int)($gym['ownerId'] ?? 0) === $userId)->count() < self::maxGymsPerTrainer;
    }

    private function buildMemberRanking(Request $request, ?array $memberGym, string $route): ?array{
        if(!$memberGym || !($memberGym['isRankingPublic'] ?? false)){
            return null;
        }
        return $this->buildRanking($request, $memberGym['id'],
            fn(int $season) => route($route, ['member' => $memberGym['id'], 'temporada' => $season]));
    }

    private function buildRanking(Request $request, int $gymId, callable $seasonUrl): ?array{
        $ranking = $this->apiClient->getGymRanking($gymId, $request->integer('temporada') ?: null);
        if(!$ranking){
            return null;
        }
        // al mostrar el desglose el backend marca leido el aviso de fin de temporada
        if($ranking['openRecap'] ?? false){
            NotificationInbox::forget();
        }
        return RankingLabels::buildPanel($ranking, $seasonUrl);
    }

    public function updateRankingVisibility(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $response = $this->apiClient->updateGymRankingVisibility($id, (bool) $validated['is_public']);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['is_public' => $this->apiErrorMessage($response, 'No pudimos guardar el ranking.')]);
        }

        return back()->with('gym_saved', $validated['is_public']
            ? 'Tus alumnos ya pueden ver el ranking.'
            : 'El ranking quedo oculto para tus alumnos.');
    }

    public function startSeason(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ], self::seasonDateMessages);

        $response = $this->apiClient->startGymSeason($id, $validated);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['season' => $this->apiErrorMessage($response, 'No pudimos iniciar la temporada.')])->withInput();
        }

        return back()->with('gym_saved', 'Temporada '.$response['data']['number'].' iniciada.');
    }

    // si la temporada ya empezo el inicio llega deshabilitado y solo se cambia el fin
    public function updateSeason(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => array_merge(['required', 'date_format:Y-m-d'], $request->filled('starts_on') ? ['after_or_equal:starts_on'] : []),
        ], self::seasonDateMessages);

        $response = $this->apiClient->updateGymSeason($id, array_filter($validated));

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['season' => $this->apiErrorMessage($response, 'No pudimos cambiar las fechas.')])->withInput();
        }

        return back()->with('gym_saved', 'Fechas de la temporada '.$response['data']['number'].' actualizadas.');
    }

    public function show(Request $request, int $id): RedirectResponse|View{
        $gym = $this->apiClient->getGym($id);
        if(!$gym){
            return redirect()->route('gym.index');
        }

        $data['gym'] = $gym;
        $data['students'] = $this->apiClient->getGymUsers($id) ?? [];
        $data['ranking'] = $this->buildRanking($request, $id, fn(int $season) => route('gym.show', [$id, 'temporada' => $season]));
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
