<?php

namespace App\Support;

class RoutineVolume
{
    private const activityNotesPlaceholder = 'Ej. pasadas de 10 metros, al terminar la rutina',
        muscleNotesPlaceholder = 'Ej. bajar lento, 2 segundos arriba';

    static function format(array $exercise): string
    {
        $sets = $exercise['sets'] ?? null;
        $reps = $exercise['reps'] ?? null;
        $minutes = $exercise['duration_minutes'] ?? null;

        $partList = [];
        if ($minutes) {
            $partList[] = $minutes.' min';
        }
        if ($sets && $reps) {
            $partList[] = $sets.' series × '.$reps.' reps';
        } elseif ($sets) {
            $partList[] = $sets.' series';
        } elseif ($reps) {
            $partList[] = $reps.' reps';
        }
        return $partList ? implode(' · ', $partList) : 'Sin indicaciones';
    }

    static function formatDetail(array $exercise, bool $isActivity): string
    {
        $restSeconds = $exercise['recommended_rest_time'] ?? null;
        return self::format($exercise).(!$isActivity && $restSeconds ? ' · '.$restSeconds.' s de descanso' : '');
    }

    static function buildNewFields(bool $isActivity): array
    {
        return self::buildFields([
            'duration_minutes' => old('duration_minutes', $isActivity ? 10 : null),
            'sets' => old('sets', $isActivity ? null : 3),
            'reps' => old('reps', $isActivity ? null : 12),
            'notes' => old('notes'),
        ], $isActivity);
    }

    static function buildFields(array $exercise, bool $isActivity = false, ?string $namePrefix = null): array
    {
        $values = [
            'duration_minutes' => $exercise['duration_minutes'] ?? null,
            'sets' => $exercise['sets'] ?? null,
            'reps' => $exercise['reps'] ?? null,
            'notes' => $exercise['notes'] ?? null,
        ];
        return [
            'values' => $values,
            'withMinutes' => filled($values['duration_minutes']),
            'withSets' => filled($values['sets']) || filled($values['reps']),
            'notesPlaceholder' => $isActivity ? self::activityNotesPlaceholder : self::muscleNotesPlaceholder,
            'names' => array_combine(array_keys($values), array_map(fn(string $key) => $namePrefix ? $namePrefix.'['.$key.']' : $key, array_keys($values))),
        ];
    }
}
