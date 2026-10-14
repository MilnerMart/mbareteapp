<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Arma la grilla del mes (semanas de lunes a domingo) y el detalle del dia elegido con lo que devuelve
 * el backend: acciones del usuario (EntityLog), dias entrenados por rutina y anotaciones/recordatorios.
 */
class CalendarMonth
{
    const timezone = 'America/Asuncion';

    const weekdayLabels = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];

    // con cuantos dias de anticipacion puede avisar un recordatorio (CalendarNote::remindDayList del backend)
    const remindDayLabelMap = [2 => '2 dias antes', 1 => 'El dia anterior', 0 => 'El mismo dia'];

    static function build(CarbonImmutable $monthStart, CarbonImmutable $selectedDay, array $calendar): array
    {
        $today = CarbonImmutable::now(self::timezone)->startOfDay();
        $actionMap = [];
        foreach ($calendar['actions'] ?? [] as $entityLog) {
            $date = CarbonImmutable::parse($entityLog['createdAt'])->setTimezone(self::timezone);
            $actionMap[$date->toDateString()][] = ActivityLabels::buildItem($entityLog, true) + ['timeLabel' => $date->format('H:i')];
        }
        $trainingMap = collect($calendar['trainings'] ?? [])->groupBy('day')->map->all()->all();
        $noteMap = collect($calendar['notes'] ?? [])->groupBy('noteOn')->map->all()->all();
        $progressMap = collect($calendar['progressEntries'] ?? [])->groupBy('entryOn')->map->all()->all();

        $weekList = [];
        $dayIndex = 0;
        $day = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $lastDay = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        while ($day <= $lastDay) {
            $date = $day->toDateString();
            $isCurrentMonth = $day->month === $monthStart->month;
            $trainingList = $isCurrentMonth ? ($trainingMap[$date] ?? []) : [];
            $noteList = $isCurrentMonth ? ($noteMap[$date] ?? []) : [];
            $weekList[intdiv($dayIndex++, 7)][] = [
                'dayNumber' => $day->day,
                'url' => self::dayUrl($day),
                'isCurrentMonth' => $isCurrentMonth,
                'isToday' => $day->equalTo($today),
                'isSelected' => $day->equalTo($selectedDay),
                'hasTraining' => (bool)$trainingList,
                'hasCompleted' => collect($trainingList)->contains('isCompleted', true),
                'actionCount' => $isCurrentMonth ? count($actionMap[$date] ?? []) : 0,
                'noteCount' => count($noteList),
                'hasProgress' => $isCurrentMonth && !empty($progressMap[$date]),
                'hasPersonalBest' => $isCurrentMonth && collect($progressMap[$date] ?? [])->contains('isPersonalBest', true),
                'hasReminder' => collect($noteList)->contains(fn(array $note) => !empty($note['remindDays'])),
                'ariaLabel' => $day->locale('es')->translatedFormat('l j \d\e F'),
            ];
            $day = $day->addDay();
        }

        $selectedDate = $selectedDay->toDateString();
        return [
            'monthLabel' => ucfirst($monthStart->locale('es')->translatedFormat('F Y')),
            'weekdayLabels' => self::weekdayLabels,
            'weekList' => $weekList,
            'previousUrl' => self::dayUrl($monthStart->subMonth()),
            'nextUrl' => self::dayUrl($monthStart->addMonth()),
            'todayUrl' => self::dayUrl($today),
            'selectedDate' => $selectedDate,
            'selectedLabel' => ucfirst($selectedDay->locale('es')->translatedFormat('l j \d\e F')),
            'trainingList' => array_map(fn(array $training) => self::buildTrainingItem($training), $trainingMap[$selectedDate] ?? []),
            'actionList' => $actionMap[$selectedDate] ?? [],
            'noteList' => array_map(fn(array $note) => self::buildNoteItem($note, $selectedDay), $noteMap[$selectedDate] ?? []),
            'progressList' => array_map(fn(array $entry) => ProgressLabels::buildEntryItem($entry), $progressMap[$selectedDate] ?? []),
        ];
    }

    static function dayUrl(CarbonImmutable $day, array $extra = []): string
    {
        return route('calendar.index', ['month' => $day->format('Y-m'), 'day' => $day->toDateString()] + $extra);
    }

    private static function buildTrainingItem(array $training): array
    {
        $routineName = '«'.($training['routineName'] ?? '-').'»';
        return [
            'icon' => $training['isCompleted'] ? 'fa-solid fa-circle-check' : 'fa-solid fa-dumbbell',
            'text' => $training['isCompleted']
                ? 'Completaste la rutina '.$routineName
                : 'Entrenaste la rutina '.$routineName.': '.$training['doneCount'].' de '.$training['totalCount'].' ejercicios',
            'url' => route('routine.show', $training['routineId']),
            'isCompleted' => (bool)$training['isCompleted'],
        ];
    }

    // [2, 0] => "Te avisamos 2 dias antes y el mismo dia"
    static function remindDaysLabel(array $remindDays): ?string
    {
        $labelList = array_values(array_map(fn(int $days) => mb_strtolower(self::remindDayLabelMap[$days]),
            array_intersect(array_keys(self::remindDayLabelMap), array_map('intval', $remindDays))));
        if(!$labelList){
            return null;
        }
        $last = array_pop($labelList);
        return 'Te avisamos '.($labelList ? implode(', ', $labelList).' y '.$last : $last);
    }

    private static function buildNoteItem(array $note, CarbonImmutable $selectedDay): array
    {
        return [
            'id' => $note['id'],
            'title' => $note['title'],
            'body' => $note['body'],
            'reminderLabel' => self::remindDaysLabel($note['remindDays'] ?? []),
            'editUrl' => self::dayUrl($selectedDay, ['edit' => $note['id']]).'#calendar-note-form',
            'deleteUrl' => route('calendar.notes.destroy', $note['id']),
        ];
    }
}
