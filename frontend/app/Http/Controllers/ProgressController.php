<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\CalendarMonth;
use App\Support\NotificationInbox;
use App\Support\ProgressLabels;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgressController extends Controller{

    private const historyLimit = 50;

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function index(): View{
        $progress = $this->apiClient->getProgress();
        $entryList = $progress['entries'] ?? [];
        $latestWeight = collect($entryList)->firstWhere('metric', 'body-weight');
        $currentWeight = $latestWeight ? (float)$latestWeight['value'] : ($progress['weight'] ?? null);

        $data['loadFailed'] = $progress === null;
        $data['today'] = CarbonImmutable::now(CalendarMonth::timezone)->toDateString();
        $data['weightCard'] = [
            'value' => $currentWeight ? ProgressLabels::formatValue('body-weight', (float)$currentWeight) : '-',
            'changeText' => $latestWeight
                ? ProgressLabels::changeText('body-weight', (float)$latestWeight['value'], $latestWeight['previousValue'] !== null ? (float)$latestWeight['previousValue'] : null)
                : null,
            'dateLabel' => $latestWeight ? 'Ultimo registro: '.Carbon::parse($latestWeight['entryOn'])->format('d/m/Y') : 'Del perfil',
        ];
        $data['bmi'] = ProgressLabels::bmi($currentWeight, $progress['height'] ?? null);
        $data['measurementList'] = $this->buildMeasurementList($entryList);
        $data['recordList'] = $this->buildRecordList($entryList);
        $data['historyList'] = array_map(fn(array $entry) => ProgressLabels::buildEntryItem($entry), array_slice($entryList, 0, self::historyLimit));
        $data['measurementOptions'] = array_combine(ProgressLabels::measurementMetricList,
            array_map(fn(string $metric) => ProgressLabels::label($metric).' ('.ProgressLabels::metricMap[$metric][1].')', ProgressLabels::measurementMetricList));
        $data['exerciseGroups'] = $this->buildExerciseGroups();
        $data['tools'] = [
            'weight' => $currentWeight ? ProgressLabels::formatNumber((float)$currentWeight) : '',
            'height' => $progress['height'] ?? '',
        ];

        return $this->renderView('progress.index', compact('data'));
    }

    public function store(Request $request): RedirectResponse{
        $validated = $request->validate([
            'entry_on' => ['required', 'date_format:Y-m-d'],
            'metric' => ['required', Rule::in(array_keys(ProgressLabels::metricMap))],
            'exercise_id' => ['nullable', 'integer'],
            'value' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'value.required' => 'Escribe el valor.',
            'value.gt' => 'El valor tiene que ser mayor a 0.',
        ]);

        $response = $this->apiClient->createProgressEntry($validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['progress' => $this->apiErrorMessage($response, 'No pudimos guardar el registro.')])->withInput();
        }
        // el peso del perfil pudo cambiar: la campanita deja de pedirlo
        NotificationInbox::forget();
        $entry = $response['data'];
        return redirect()->route('progress.index')->with('progress_saved', $entry['isPersonalBest']
            ? '¡'.ProgressLabels::entryText($entry).'! Es tu nuevo record personal.'
            : 'Registro guardado.');
    }

    public function destroy(int $id): RedirectResponse{
        $response = $this->apiClient->deleteProgressEntry($id);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['progress' => $this->apiErrorMessage($response, 'No pudimos eliminar el registro.')]);
        }
        return redirect()->route('progress.index')->with('progress_saved', 'Registro eliminado.');
    }

    // ejercicios agrupados por musculo para el buscador: ['Biceps' => [id => nombre], ...]
    private function buildExerciseGroups(): array{
        $muscleNameMap = collect($this->apiClient->getMuscles() ?? [])->pluck('name', 'id')->all();
        return collect($this->apiClient->getExercises() ?? [])
            ->groupBy(fn(array $exercise) => $muscleNameMap[$exercise['muscle_id'] ?? 0] ?? 'Otros')
            ->map(fn($exerciseList) => $exerciseList->sortBy('name')->pluck('name', 'id')->all())
            ->sortKeys()
            ->all();
    }

    // ultima medida de cada tipo con su cambio respecto de la anterior (las entradas vienen de la mas nueva a la mas vieja)
    private function buildMeasurementList(array $entryList): array{
        $latestMap = collect($entryList)->whereIn('metric', ProgressLabels::measurementMetricList)->unique('metric')->keyBy('metric');
        $measurementList = [];
        foreach (ProgressLabels::measurementMetricList as $metric) {
            $entry = $latestMap[$metric] ?? null;
            if($entry){
                $measurementList[] = [
                    'label' => ProgressLabels::label($metric),
                    'value' => ProgressLabels::formatValue($metric, (float)$entry['value']),
                    'changeText' => ProgressLabels::changeText($metric, (float)$entry['value'], $entry['previousValue'] !== null ? (float)$entry['previousValue'] : null),
                    'dateLabel' => Carbon::parse($entry['entryOn'])->format('d/m/Y'),
                ];
            }
        }
        return $measurementList;
    }

    // mejor marca de kilos y de repeticiones por ejercicio
    private function buildRecordList(array $entryList): array{
        $recordList = [];
        foreach (collect($entryList)->whereIn('metric', ProgressLabels::recordMetricList)->groupBy('exerciseId') as $exerciseEntries) {
            $bestList = [];
            foreach (ProgressLabels::recordMetricList as $metric) {
                $best = $exerciseEntries->where('metric', $metric)->sortByDesc('value')->first();
                $bestList[$metric] = $best ? [
                    'value' => ProgressLabels::formatValue($metric, (float)$best['value']),
                    'dateLabel' => Carbon::parse($best['entryOn'])->format('d/m/Y'),
                ] : null;
            }
            $recordList[] = ['exerciseName' => $exerciseEntries->first()['exerciseName'] ?? 'Ejercicio', 'best' => $bestList];
        }
        usort($recordList, fn(array $a, array $b) => strcmp($a['exerciseName'], $b['exerciseName']));
        return $recordList;
    }
}
