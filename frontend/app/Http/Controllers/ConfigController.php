<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\ProfileLabels;
use App\Support\SystemRuleLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfigController extends Controller
{
    /**
     * Apartados de configuracion de la app. Para sumar uno nuevo basta con agregarlo aca;
     * route queda en null hasta que el apartado tenga su propia pantalla.
     */
    private const sectionList = [
        [
            'key' => 'core',
            'title' => 'Credenciales de la app',
            'description' => 'Que credencial usa la app para cada accion: enviar correos, guardar y obtener recursos.',
            'icon' => 'fa-solid fa-sliders',
            'route' => 'config.core',
        ],
        [
            'key' => 'providers',
            'title' => 'Proveedores',
            'description' => 'Servicios externos a los que se conecta la app y sus credenciales.',
            'icon' => 'fa-solid fa-plug',
            'route' => 'config.providers',
        ],
        [
            'key' => 'progress',
            'title' => 'Rangos y puntos',
            'description' => 'Cuantos puntos da entrenar y que hace falta para subir a cada rango.',
            'icon' => 'fa-solid fa-medal',
            'route' => 'config.progress',
        ],
        [
            'key' => 'rules',
            'title' => 'Reglas del sistema',
            'description' => 'Tareas que la app corre sola, cuando corren y que hicieron cada vez.',
            'icon' => 'fa-solid fa-clock-rotate-left',
            'route' => 'config.rules',
        ],
    ];

    // como suma puntos cada cosa (ProgressSettings::defaultScoring del backend)
    const scoringFieldMap = [
        'pointsPerDay' => ['label' => 'Puntos por dia entrenado', 'min' => 0],
        'pointsPerStreakDay' => ['label' => 'Puntos extra por dia de racha', 'min' => 0],
        'pointsPerMinute' => ['label' => 'Puntos por minuto entrenado', 'min' => 0],
        'kgPerPoint' => ['label' => 'Kilos levantados por cada punto', 'min' => 1],
        'maxPointsPerDay' => ['label' => 'Tope de puntos por dia', 'min' => 1],
    ];

    const capabilityMap = [
        'resources' => 'Recursos',
        'messaging' => 'Mensajeria',
        'public-resources' => 'Recursos publicos',
    ];

    const channelMap = [
        'email' => 'Email',
        'sms' => 'SMS',
        'push' => 'Push',
        'whatsapp' => 'WhatsApp',
    ];

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(): View{
        $data['sections'] = self::sectionList;
        return $this->renderView('config.index', compact('data'));
    }

    public function progress(): View{
        $settings = $this->apiClient->getProgressSettings();
        $data['loadFailed'] = !$settings;
        $data['scoringFields'] = array_map(fn(string $key, array $field) => $field + [
            'key' => $key,
            'value' => old('scoring.'.$key, $settings['scoring'][$key] ?? ''),
        ], array_keys(self::scoringFieldMap), self::scoringFieldMap);
        $data['rankRows'] = array_map(fn(string $slug, array $rank) => [
            'slug' => $slug,
            'label' => ProfileLabels::rankLabel($slug),
            'minPoints' => old('ranks.'.$slug.'.minPoints', $rank['minPoints']),
            // el primer rango es el de entrada: siempre 0 puntos
            'isFixed' => $slug === array_key_first($settings['ranks']),
        ], array_keys($settings['ranks'] ?? []), $settings['ranks'] ?? []);
        return $this->renderView('config.progress', compact('data'));
    }

    // se guarda todo junto en un solo pedido
    public function updateProgress(Request $request): RedirectResponse{
        $validated = $request->validate([
            'scoring' => ['required', 'array'],
            'scoring.*' => ['required', 'integer', 'min:0'],
            'scoring.kgPerPoint' => ['required', 'integer', 'min:1'],
            'scoring.maxPointsPerDay' => ['required', 'integer', 'min:1'],
            'ranks' => ['required', 'array'],
            'ranks.*.minPoints' => ['required', 'integer', 'min:0'],
        ], [
            'scoring.*.required' => 'Completa todos los puntos.',
            'ranks.*.*.required' => 'Completa los puntos de cada rango.',
            'scoring.kgPerPoint.min' => 'Los kilos por punto tienen que ser al menos 1.',
            'scoring.maxPointsPerDay.min' => 'El tope de puntos por dia tiene que ser al menos 1.',
        ]);

        $response = $this->apiClient->updateProgressSettings($validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['progress' => $this->apiErrorMessage($response, 'No pudimos guardar los rangos.')])->withInput();
        }
        return redirect()->route('config.progress')->with('config_saved', 'Rangos y puntos guardados. Los niveles de todos se recalculan con estos valores.');
    }

    public function rules(): View{
        $ruleList = $this->apiClient->getSystemRules();
        $data['loadFailed'] = $ruleList === null;
        $data['rules'] = SystemRuleLabels::buildRuleList($ruleList ?? []);
        return $this->renderView('config.rules', compact('data'));
    }

    // todas las reglas se guardan juntas en un solo pedido
    public function updateRules(Request $request): RedirectResponse{
        $validated = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.is_enabled' => ['required', 'boolean'],
            'rules.*.frequency' => ['required', Rule::in(array_keys(SystemRuleLabels::frequencyMap))],
            'rules.*.run_at' => ['required', 'date_format:H:i'],
            'rules.*.retry_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ], [
            'rules.*.run_at.required' => 'Elige a que hora corre cada regla.',
            'rules.*.run_at.date_format' => 'La hora tiene que tener el formato HH:MM.',
            'rules.*.retry_minutes.*' => 'Los minutos de reintento van de 0 (no reintenta) a 1440 (un dia).',
        ]);

        $response = $this->apiClient->updateSystemRules($validated['rules']);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['rules' => $this->apiErrorMessage($response, 'No pudimos guardar las reglas.')])->withInput();
        }
        return redirect()->route('config.rules')->with('config_saved', 'Reglas guardadas. Corren solas desde su proximo horario.');
    }

    public function runRule(int $ruleModelId): RedirectResponse{
        $response = $this->apiClient->runSystemRule($ruleModelId);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['rules' => $this->apiErrorMessage($response, 'No pudimos ejecutar la regla.')]);
        }
        $run = $response['data']['lastRun'] ?? [];
        return match ($run['status'] ?? null) {
            'completed' => back()->with('config_saved', 'Regla ejecutada: '.SystemRuleLabels::summaryText($response['data']['slug'] ?? '', $run['summary'] ?? []).'.'),
            'skipped' => back()->withErrors(['rules' => 'La regla ya se estaba ejecutando. Mira el resultado en un momento.']),
            default => back()->withErrors(['rules' => 'La regla fallo: '.($run['error'] ?? 'error desconocido')]),
        };
    }

    public function providers(): View{
        $data['types'] = $this->apiClient->getConfigProviderTypes() ?? [];
        $data['providers'] = $this->apiClient->getConfigProviders() ?? [];
        $data['capabilities'] = self::capabilityMap;
        return $this->renderView('config.providers', compact('data'));
    }

    public function storeProvider(Request $request): RedirectResponse{
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'base_url' => ['nullable', 'url', 'max:255'],
        ]);

        $response = $this->apiClient->createConfigProvider($validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['provider' => $this->apiErrorMessage($response, 'No pudimos crear el proveedor.')])->withInput();
        }

        return redirect()->route('config.providers.show', $response['data']['id'])
            ->with('config_saved', 'Proveedor creado. Ahora agregale una credencial.');
    }

    public function showProvider(int $id): RedirectResponse|View{
        $provider = $this->apiClient->getConfigProvider($id);
        if(!$provider){
            return redirect()->route('config.providers');
        }

        $data['provider'] = $provider;
        $data['capabilities'] = self::capabilityMap;
        $data['channels'] = self::channelMap;
        return $this->renderView('config.provider', compact('data'));
    }

    public function updateProvider(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
        ]);

        $response = $this->apiClient->updateConfigProvider($id, $validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['provider' => $this->apiErrorMessage($response, 'No pudimos actualizar el proveedor.')])->withInput();
        }

        return back()->with('config_saved', 'Proveedor actualizado.');
    }

    public function destroyProvider(int $id): RedirectResponse{
        $response = $this->apiClient->deleteConfigProvider($id);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['provider' => $this->apiErrorMessage($response, 'No pudimos eliminar el proveedor.')]);
        }

        return redirect()->route('config.providers')->with('config_saved', 'Proveedor eliminado.');
    }

    public function storeProviderConfig(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'config_name' => ['required', 'string', 'min:2', 'max:100'],
            'credentials' => ['nullable', 'array', 'max:20'],
            'credentials.*.key' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'credentials.*.value' => ['nullable', 'string', 'max:4000'],
            'settings' => ['nullable', 'array', 'max:20'],
            'settings.*.key' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'settings.*.value' => ['nullable', 'string', 'max:255'],
        ], [
            'credentials.*.key.regex' => 'El nombre de cada credencial solo puede tener letras, numeros, punto, guion o guion bajo.',
            'settings.*.key.regex' => 'El nombre de cada ajuste solo puede tener letras, numeros, punto, guion o guion bajo.',
        ]);

        $response = $this->apiClient->createProviderConfig($id, [
            'name' => $validated['config_name'],
            'credentials' => $this->rowsToMap($validated['credentials'] ?? []),
            'settings' => $this->rowsToMap($validated['settings'] ?? []),
        ]);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['provider_config' => $this->apiErrorMessage($response, 'No pudimos guardar la credencial.')])
                ->withInput($request->except('credentials'));
        }

        return back()->with('config_saved', 'Credencial guardada.');
    }

    public function destroyProviderConfig(int $id): RedirectResponse{
        $response = $this->apiClient->deleteProviderConfig($id);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['provider_config' => $this->apiErrorMessage($response, 'No pudimos eliminar la credencial.')]);
        }

        return back()->with('config_saved', 'Credencial eliminada.');
    }

    public function core(): View{
        $data['actions'] = $this->apiClient->getCoreConfig() ?? [];
        $data['capabilities'] = self::capabilityMap;
        $data['channels'] = self::channelMap;
        return $this->renderView('config.core', compact('data'));
    }

    public function updateCore(Request $request): RedirectResponse{
        $validated = $request->validate([
            'actions' => ['required', 'array'],
            'actions.*' => ['nullable', 'integer'],
        ]);

        $response = $this->apiClient->updateCoreConfig($validated['actions']);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['core' => $this->apiErrorMessage($response, 'No pudimos guardar la configuracion.')]);
        }

        return back()->with('config_saved', 'Configuracion guardada.');
    }

    /**
     * Filas [key, value] del formulario a ['key' => 'value'], sin las que quedaron vacias.
     */
    private function rowsToMap(array $rowList): array{
        $map = [];
        foreach ($rowList as $row) {
            $key = trim($row['key'] ?? '');
            if($key !== '' && ($row['value'] ?? '') !== ''){
                $map[$key] = $row['value'];
            }
        }
        return $map;
    }
}
