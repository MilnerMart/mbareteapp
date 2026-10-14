<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller{

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    /**
     * Mes con lo que hizo el usuario y sus anotaciones. ?month=Y-m&day=Y-m-d elige el dia del detalle; ?edit={id} abre una anotacion.
     */
    public function index(Request $request): View{
        $today = CarbonImmutable::now(CalendarMonth::timezone)->startOfDay();
        $monthStart = $this->parseDate($request->query('month'), '!Y-m') ?? $today->startOfMonth();
        $selectedDay = $this->parseDate($request->query('day'), '!Y-m-d');
        if(!$selectedDay || $selectedDay->format('Y-m') !== $monthStart->format('Y-m')){
            $selectedDay = $today->format('Y-m') === $monthStart->format('Y-m') ? $today : $monthStart;
        }

        $calendar = $this->apiClient->getCalendar($monthStart->format('Y-m')) ?? [];
        $data = CalendarMonth::build($monthStart, $selectedDay, $calendar);
        $data['loadFailed'] = $calendar === [];
        $editNote = collect($calendar['notes'] ?? [])->firstWhere('id', (int)$request->query('edit'));
        $data['form'] = [
            'action' => $editNote ? route('calendar.notes.update', $editNote['id']) : route('calendar.notes.store'),
            'isEdit' => (bool)$editNote,
            'cancelUrl' => CalendarMonth::dayUrl($selectedDay),
            // el dia lo define el dia elegido del calendario (o el de la anotacion al editarla) y no se cambia
            'noteOn' => $editNote ? null : $selectedDay->toDateString(),
            'dayLabel' => $editNote
                ? ucfirst(CarbonImmutable::parse($editNote['noteOn'], CalendarMonth::timezone)->locale('es')->translatedFormat('l j \d\e F'))
                : $data['selectedLabel'],
            'values' => [
                'title' => old('title', $editNote['title'] ?? ''),
                'body' => old('body', $editNote['body'] ?? ''),
            ],
            'remindDays' => array_map('intval', old('remind_days', $editNote['remindDays'] ?? [])),
            'remindDayOptions' => CalendarMonth::remindDayLabelMap,
        ];

        return $this->renderView('calendar.index', compact('data'));
    }

    public function store(Request $request): RedirectResponse{
        $validated = $request->validate(['note_on' => ['required', 'date_format:Y-m-d']]) + $this->validateNote($request);
        $response = $this->apiClient->createCalendarNote($validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['note' => $this->apiErrorMessage($response, 'No pudimos guardar la anotacion.')])->withInput();
        }
        $remindLabel = CalendarMonth::remindDaysLabel($validated['remind_days']);
        return $this->redirectToDay($validated['note_on'], 'Anotacion guardada.'.($remindLabel ? ' '.$remindLabel.' en la campanita.' : ''));
    }

    public function update(Request $request, int $id): RedirectResponse{
        $validated = $this->validateNote($request);
        $response = $this->apiClient->updateCalendarNote($id, $validated);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['note' => $this->apiErrorMessage($response, 'No pudimos guardar la anotacion.')])->withInput();
        }
        return $this->redirectToDay($response['data']['noteOn'], 'Anotacion actualizada.');
    }

    public function destroy(Request $request, int $id): RedirectResponse{
        $response = $this->apiClient->deleteCalendarNote($id);
        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['note' => $this->apiErrorMessage($response, 'No pudimos eliminar la anotacion.')]);
        }
        return back()->with('calendar_saved', 'Anotacion eliminada.');
    }

    private function validateNote(Request $request): array{
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'body' => ['nullable', 'string', 'max:1000'],
            'remind_days' => ['nullable', 'array'],
            'remind_days.*' => ['integer', Rule::in(array_keys(CalendarMonth::remindDayLabelMap))],
        ], [
            'title.required' => 'Escribe un titulo para la anotacion.',
            'title.min' => 'El titulo debe tener al menos 2 letras.',
            'remind_days.*.in' => 'Elige cuando quieres que te avisemos.',
        ]) + ['body' => null, 'remind_days' => []];
    }

    private function redirectToDay(string $noteOn, string $message): RedirectResponse{
        return redirect(CalendarMonth::dayUrl(CarbonImmutable::parse($noteOn, CalendarMonth::timezone)))->with('calendar_saved', $message);
    }

    private function parseDate(?string $value, string $format): ?CarbonImmutable{
        if(!$value){
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat($format, $value, CalendarMonth::timezone);
        } catch (\Throwable) {
            return null;
        }
        return $date && $date->format(ltrim($format, '!')) === $value ? $date : null;
    }
}
