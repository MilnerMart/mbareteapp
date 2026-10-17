<?php

namespace App\Http\Controllers;

use App\Rules\PersonName;
use App\Services\ApiClientService;
use App\Support\AchievementLabels;
use App\Support\ActivityLabels;
use App\Support\AuthPermits;
use App\Support\NotificationInbox;
use App\Support\ProfileLabels;
use App\Support\TicketLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller{

    private const minDeactivationReasonLength = 5;

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
        $data['progress'] = $this->buildProgressModel($userInfo['progress'] ?? [], $userInfo['gender'] ?? null, $userInfo['rest_days'] ?? []);
        $data['favorites'] = $this->buildFavoriteList($userInfo['favorites'] ?? [], $data['isOwnProfile']);
        $data['form'] = $data['isOwnProfile'] ? $this->buildProfileForm($userInfo) : null;
        $data['achievements'] = AchievementLabels::buildPanel($userInfo['achievements'] ?? [], $data['isOwnProfile'], $userInfo['gender'] ?? null);
        // ?logros=1 llega desde el aviso de la campanita y abre la lista de logros
        $data['openAchievements'] = request()->boolean('logros');

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

    // ficha de solo lectura que el admin abre desde la tarjeta del alumno; se carga dentro de un modal
    public function detail(int $id): Response{
        $userInfo = $this->apiClient->getUserDetail($id);
        if(!$userInfo){
            return response('No pudimos cargar el perfil del usuario.', 404);
        }

        $data['profile'] = $this->buildProfileModel($userInfo);
        $data['progress'] = $this->buildProgressModel($userInfo['progress'] ?? [], $userInfo['gender'] ?? null, $userInfo['rest_days'] ?? []);
        $data['favorites'] = $this->buildFavoriteList($userInfo['favorites'] ?? [], false);
        $data['infoList'] = [
            ['label' => 'Correo', 'value' => ($userInfo['email'] ?? '-').(($userInfo['email_verified'] ?? false) ? '' : ' (sin confirmar)')],
            ['label' => 'Genero', 'value' => ProfileLabels::genderLabel($userInfo['gender'] ?? null) ?? 'Sin definir'],
            ['label' => 'Objetivo', 'value' => ProfileLabels::goalLabel($userInfo['goal'] ?? null) ?? 'Sin definir'],
            ['label' => 'Roles', 'value' => implode(', ', $userInfo['roles'] ?? []) ?: '-'],
            ['label' => 'Gimnasios', 'value' => implode(', ', $userInfo['gyms'] ?? []) ?: '-'],
            ['label' => 'Miembro desde', 'value' => TicketLabels::formatDate($userInfo['created_at'] ?? null)],
        ];
        $data['routines'] = array_map(fn(array $routine) => [
            'name' => $routine['name'],
            'url' => route('routine.show', $routine['id']),
        ], $userInfo['routines'] ?? []);
        $pendingLabelList = array_filter(array_map(fn(string $field) => ProfileLabels::profileFieldLabelMap[$field] ?? null, $userInfo['pending_profile_fields'] ?? []));
        // el registro de actividad sigue siendo solo del admin
        $data['activityUrl'] = AuthPermits::isAdmin() ? route('user.activity', $id) : null;
        $data['pendingText'] = $pendingLabelList ? 'Datos sin completar: '.implode(', ', $pendingLabelList) : null;
        $data['deactivation'] = $this->buildDeactivationModel($userInfo['deactivation'] ?? null);
        $data['deactivateForm'] = ($userInfo['can_deactivate'] ?? false) ? [
            'url' => route('user.deactivate', $id),
            'confirmText' => $data['profile']['fullName'].' no podra volver a iniciar sesion y dejara de aparecer en las listas de alumnos y rankings.',
            'minLength' => self::minDeactivationReasonLength,
        ] : null;

        return response()->view('users._detail', compact('data'));
    }

    // baja logica desde la ficha del alumno: el motivo queda registrado y se ve en su ficha
    public function deactivate(Request $request, int $id): RedirectResponse{
        $request->merge(['reason' => trim((string)$request->input('reason'))]);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:'.self::minDeactivationReasonLength, 'max:500'],
        ], [
            'reason.required' => 'Indica el motivo de la baja.',
            'reason.min' => 'El motivo de la baja tiene que tener al menos '.self::minDeactivationReasonLength.' caracteres.',
            'reason.max' => 'El motivo de la baja puede tener hasta 500 caracteres.',
        ]);

        $response = $this->apiClient->deactivateUser($id, $validated['reason']);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['student' => $this->apiErrorMessage($response, 'No pudimos dar de baja al usuario.')]);
        }

        $fullName = trim(($response['data']['name'] ?? '').' '.($response['data']['last_name'] ?? '')) ?: 'El usuario';
        return back()->with('gym_saved', $fullName.' fue dado de baja.');
    }

    private function buildDeactivationModel(?array $deactivation): ?array{
        if(!$deactivation){
            return null;
        }
        return [
            'title' => 'Dado de baja el '.TicketLabels::formatDate($deactivation['at'] ?? null).(empty($deactivation['byName']) ? '' : ' por '.$deactivation['byName']),
            'reason' => $deactivation['reason'] ?? '-',
        ];
    }

    // registro de actividad del usuario, solo para el admin
    public function activity(Request $request, int $id): RedirectResponse|View{
        $model = array_key_exists($request->query('model'), ActivityLabels::modelFilterMap) ? $request->query('model') : null;
        $page = max(1, (int)$request->query('page', 1));
        $response = $this->apiClient->getUserLogs($id, $page, $model);
        if(!$response){
            return back()->with('auth_error', 'No pudimos cargar el registro de actividad. Intenta de nuevo.');
        }

        $user = $response['user'];
        $data['userName'] = trim(($user['name'] ?? '').' '.($user['last_name'] ?? '')) ?: 'Usuario';
        $data['userImageUrl'] = $user['profile_image_url'] ?? asset('images/chavukuBiceps.png');
        $data['dayList'] = ActivityLabels::buildDayList($response['items'] ?? []);
        $data['filterList'] = array_map(fn(?string $filterModel, string $label) => [
            'label' => $label,
            'url' => route('user.activity', array_filter(['id' => $id, 'model' => $filterModel])),
            'isActive' => $filterModel === $model,
        ], [null, ...array_keys(ActivityLabels::modelFilterMap)], ['Todo', ...array_values(ActivityLabels::modelFilterMap)]);
        $data['previousUrl'] = $page > 1 ? route('user.activity', array_filter(['id' => $id, 'model' => $model, 'page' => $page - 1])) : null;
        $data['nextUrl'] = ($response['hasMore'] ?? false) ? route('user.activity', array_filter(['id' => $id, 'model' => $model, 'page' => $page + 1])) : null;
        $data['emptyText'] = $model ? 'No hay actividad de este tipo.' : 'Todavia no hay actividad registrada.';

        return $this->renderView('users.activity', compact('data'));
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
            'rest_days' => ['nullable', 'array', 'max:'.ProfileLabels::maxRestDays],
            'rest_days.*' => ['integer', 'distinct', Rule::in(array_keys(ProfileLabels::weekDayLabelMap))],
        ], [
            'rest_days.max' => 'Deja al menos un dia de entrenamiento en la semana.',
        ]);
        // el select multiple no envia nada si se quitan todos: se manda vacio para que vuelva a pedir todos los dias
        $validated['rest_days'] = array_map('intval', $validated['rest_days'] ?? []);

        $response = $this->apiClient->updateProfile($id, $validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['profile' => $this->apiErrorMessage($response, 'No pudimos guardar tu perfil.')])->withInput();
        }

        $request->session()->put('auth_user', array_merge(session('auth_user', []), [
            'name' => $response['data']['name'],
            'last_name' => $response['data']['last_name'],
        ]));
        // pudo completar datos que la campanita le pedia
        NotificationInbox::forget();
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
            'imageUrl' => $userInfo['profile_image_url'] ?? asset('images/chavukuBiceps.png'),
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
    private function buildProgressModel(array $progress, ?string $gender, array $restDayList): array{
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
            'restDaysText' => ProfileLabels::restDaysText($restDayList),
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
                'gender' => empty($userInfo['gender']) ? old('gender', '') : $userInfo['gender'],
            ],
            'restDayOptions' => $this->buildRestDayOptionList(array_map('intval', (array)old('rest_days', $userInfo['rest_days'] ?? []))),
            'goalOptions' => ProfileLabels::goalLabelMap,
            'genderOptions' => ProfileLabels::genderLabelMap,
            'isGenderLocked' => !empty($userInfo['gender']),
            'genderHint' => empty($userInfo['gender'])
                ? 'Define como se nombra tu rango (Novato o Novata, por ejemplo). Una vez guardado no se puede cambiar.'
                : 'Ya lo definiste y no se puede cambiar.',
            'confirmText' => empty($userInfo['gender'])
                ? 'Se actualizan tus datos, tu objetivo y tu genero. El genero no se puede cambiar despues.'
                : 'Se actualizan tus datos y tu objetivo.',
            // ?edit=1 llega desde la campanita cuando faltan datos del perfil
            'isOpen' => request()->boolean('edit') || (bool)session('errors')?->hasAny(['profile', 'name', 'last_name', 'age', 'height', 'weight', 'goal', 'gender', 'rest_days', 'rest_days.*']),
        ];
    }

    private function buildRestDayOptionList(array $restDayList): array{
        return array_map(fn(int $day, string $label) => [
            'value' => $day,
            'label' => $label,
            'isSelected' => in_array($day, $restDayList, true),
        ], array_keys(ProfileLabels::weekDayLabelMap), ProfileLabels::weekDayLabelMap);
    }
}
