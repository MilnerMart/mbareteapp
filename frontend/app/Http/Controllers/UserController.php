<?php

namespace App\Http\Controllers;

use App\Rules\PersonName;
use App\Services\ApiClientService;
use App\Support\AuthPermits;
use App\Support\ProfileLabels;
use App\Support\TicketLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller{

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }
    public function getProfile(int $id){
        $userInfo = $this->apiClient->getUser($id);
        if(!$userInfo){
            return redirect()->route('muscle.index');
        }

        $data['isOwnProfile'] = (int) session('auth_user.id') === $id;
        $data['profile'] = $this->buildProfileModel($userInfo);
        $data['progress'] = $this->buildProgressModel($userInfo['progress'] ?? [], $userInfo['gender'] ?? null);
        $data['favorites'] = $this->buildFavoriteList($userInfo['favorites'] ?? [], $data['isOwnProfile']);
        $data['form'] = $data['isOwnProfile'] ? $this->buildProfileForm($userInfo) : null;

        if($data['isOwnProfile'] && ($authUser = $this->apiClient->me())){
            // si le aprobaron el alta de entrenador, los permisos nuevos se reflejan sin volver a loguearse
            session()->put('auth_user', array_merge(session('auth_user', []), $authUser));
        }
        // el entrenador ve sus solicitudes en Solicitudes > Enviadas, en el perfil solo las ve el alumno
        $ticketList = $data['isOwnProfile'] && !AuthPermits::canManageGyms() ? ($this->apiClient->getMyTickets() ?? []) : [];
        $data['tickets'] = array_map(fn(array $ticket) => $ticket + [
            'summary' => TicketLabels::requesterSummary($ticket),
            'stateLabel' => TicketLabels::stateLabel($ticket['state'] ?? null),
        ], $ticketList);

        return $this->renderView('users.profile', compact('data'));
    }

    public function updateProfile(Request $request, int $id): RedirectResponse{
        if((int) session('auth_user.id') !== $id){
            return redirect()->route('user.profile', $id);
        }
        $request->merge([
            'name' => PersonName::normalize($request->input('name')),
            'last_name' => PersonName::normalize($request->input('last_name')),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', new PersonName('nombre')],
            'last_name' => ['required', 'string', 'min:2', 'max:60', new PersonName('apellido')],
            'age' => ['required', 'integer', 'min:12', 'max:100'],
            'height' => ['required', 'integer', 'min:100', 'max:230'],
            'weight' => ['required', 'integer', 'min:25', 'max:250'],
            'goal' => ['nullable', Rule::in(array_keys(ProfileLabels::goalLabelMap))],
            'gender' => ['nullable', Rule::in(array_keys(ProfileLabels::genderLabelMap))],
        ]);

        $response = $this->apiClient->updateProfile($id, $validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['profile' => $this->apiErrorMessage($response, 'No pudimos guardar tu perfil.')])->withInput();
        }

        $request->session()->put('auth_user', array_merge(session('auth_user', []), [
            'name' => $response['data']['name'],
            'last_name' => $response['data']['last_name'],
        ]));
        return redirect()->route('user.profile', $id)->with('profile_saved', 'Perfil actualizado.');
    }

    public function updateProfileImage(Request $request, int $id){
        $validated = $request->validate([
            'profile_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $response = $this->apiClient->updateProfileImage($id, $validated['profile_image']);

        if(!($response['success'] ?? false)){
            return back()->withErrors([
                'profile_image' => $response['message'] ?? 'No pudimos actualizar la foto de perfil.',
            ]);
        }

        if((int) session('auth_user.id') === $id){
            // conserva roles y permisos que no vienen en la respuesta del perfil
            $request->session()->put('auth_user', array_merge(session('auth_user', []), $response['data']));
        }

        return back()->with('profile_image_updated', 'Foto de perfil actualizada.');
    }

    private function buildProfileModel(array $userInfo): array{
        return [
            'id' => $userInfo['id'],
            'fullName' => trim(($userInfo['name'] ?? '').' '.($userInfo['last_name'] ?? '')) ?: 'Usuario',
            'imageUrl' => $userInfo['profile_image_url'] ?? asset('images/leoncioBiceps.png'),
            'goalLabel' => ProfileLabels::goalLabel($userInfo['goal'] ?? null),
            'rankLabel' => ProfileLabels::rankLabel($userInfo['progress']['rank'] ?? null, $userInfo['gender'] ?? null),
            'statList' => [
                ['label' => 'Edad', 'value' => isset($userInfo['age']) ? $userInfo['age'].' años' : '-'],
                ['label' => 'Altura', 'value' => isset($userInfo['height']) ? $userInfo['height'].' cm' : '-'],
                ['label' => 'Peso', 'value' => isset($userInfo['weight']) ? $userInfo['weight'].' kg' : '-'],
            ],
        ];
    }

    // nivel calculado con puntos por dias entrenados, racha, minutos y kilos (lo calcula el backend)
    private function buildProgressModel(array $progress, ?string $gender): array{
        $points = (int)($progress['points'] ?? 0);
        $nextRankPoints = $progress['nextRankPoints'] ?? null;
        $currentStreak = (int)($progress['currentStreak'] ?? 0);
        $bestStreak = (int)($progress['bestStreak'] ?? 0);
        return [
            'rankLabel' => ProfileLabels::rankLabel($progress['rank'] ?? null, $gender),
            'points' => number_format($points, 0, ',', '.'),
            'progressPercent' => min(100, max(0, (int)($progress['progressPercent'] ?? 0))),
            'nextText' => $nextRankPoints
                ? 'Faltan '.number_format($nextRankPoints - $points, 0, ',', '.').' puntos para '.ProfileLabels::rankLabel($progress['nextRank'] ?? null, $gender)
                : 'Llegaste al rango maximo',
            'metricList' => [
                ['icon' => 'fa-solid fa-calendar-check', 'label' => 'Dias entrenados', 'value' => (int)($progress['trainingDays'] ?? 0)],
                ['icon' => 'fa-solid fa-fire', 'label' => 'Racha actual', 'value' => $currentStreak.' dia'.($currentStreak === 1 ? '' : 's')],
                ['icon' => 'fa-solid fa-trophy', 'label' => 'Mejor racha', 'value' => $bestStreak.' dia'.($bestStreak === 1 ? '' : 's')],
                ['icon' => 'fa-regular fa-clock', 'label' => 'Tiempo entrenado', 'value' => ProfileLabels::formatMinutes((int)($progress['minutes'] ?? 0))],
                ['icon' => 'fa-solid fa-weight-hanging', 'label' => 'Kilos levantados', 'value' => number_format((int)($progress['kilos'] ?? 0), 0, ',', '.').' kg'],
            ],
        ];
    }

    // rutina, grupo muscular y ejercicio que mas entrena: los calcula el backend con lo que marca como hecho
    private function buildFavoriteList(array $favorites, bool $isOwnProfile): array{
        $routine = $favorites['routine'] ?? null;
        $muscle = $favorites['muscle'] ?? null;
        $exercise = $favorites['exercise'] ?? null;
        $emptyText = $isOwnProfile ? 'Marca ejercicios como hechos en tus rutinas y aparecera aca.' : 'Todavia sin datos.';
        return [
            [
                'title' => 'Rutina favorita',
                'icon' => 'fa-solid fa-list-check',
                'name' => $routine['name'] ?? null,
                'detail' => $routine ? $routine['exerciseCount'].' ejercicio'.($routine['exerciseCount'] === 1 ? '' : 's') : null,
                'url' => $routine ? route('routine.show', $routine['id']) : null,
                'imageUrl' => null,
                'imageAttribution' => null,
                'emptyText' => $emptyText,
            ],
            [
                'title' => ($muscle['kind'] ?? null) === 'activity' ? 'Actividad favorita' : 'Grupo muscular favorito',
                'icon' => 'fa-solid fa-dumbbell',
                'name' => $muscle['name'] ?? null,
                'detail' => null,
                'url' => $muscle ? route('exercise.group', $muscle['id']) : null,
                'imageUrl' => $muscle['image_url'] ?? null,
                'imageAttribution' => null,
                'emptyText' => $emptyText,
            ],
            [
                'title' => 'Ejercicio favorito',
                'icon' => 'fa-solid fa-person-running',
                'name' => $exercise['name'] ?? null,
                'detail' => null,
                'url' => $exercise ? route('exercise.resource', $exercise['id']) : null,
                'imageUrl' => $exercise['image_url'] ?? null,
                'imageAttribution' => $exercise['imageAttribution'] ?? null,
                'emptyText' => $emptyText,
            ],
        ];
    }

    private function buildProfileForm(array $userInfo): array{
        return [
            'values' => [
                'name' => old('name', $userInfo['name'] ?? ''),
                'last_name' => old('last_name', $userInfo['last_name'] ?? ''),
                'age' => old('age', $userInfo['age'] ?? ''),
                'height' => old('height', $userInfo['height'] ?? ''),
                'weight' => old('weight', $userInfo['weight'] ?? ''),
                'goal' => old('goal', $userInfo['goal'] ?? ''),
                'gender' => old('gender', $userInfo['gender'] ?? ''),
            ],
            'goalOptions' => ProfileLabels::goalLabelMap,
            'genderOptions' => ProfileLabels::genderLabelMap,
            'isOpen' => (bool)session('errors')?->hasAny(['profile', 'name', 'last_name', 'age', 'height', 'weight', 'goal', 'gender']),
        ];
    }
}
