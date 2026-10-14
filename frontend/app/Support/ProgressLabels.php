<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Textos de los registros de progreso (peso, medidas y records) que el backend manda como metric + value.
 */
class ProgressLabels
{
    // metric => [nombre, unidad, icono]
    const metricMap = [
        'body-weight' => ['Peso', 'kg', 'fa-solid fa-weight-scale'],
        'waist' => ['Cintura', 'cm', 'fa-solid fa-ruler-horizontal'],
        'hip' => ['Cadera', 'cm', 'fa-solid fa-ruler-horizontal'],
        'chest' => ['Pecho', 'cm', 'fa-solid fa-ruler-horizontal'],
        'arm' => ['Brazo', 'cm', 'fa-solid fa-ruler-horizontal'],
        'thigh' => ['Muslo', 'cm', 'fa-solid fa-ruler-horizontal'],
        'calf' => ['Pantorrilla', 'cm', 'fa-solid fa-ruler-horizontal'],
        'neck' => ['Cuello', 'cm', 'fa-solid fa-ruler-horizontal'],
        'body-fat' => ['Grasa corporal', '%', 'fa-solid fa-droplet'],
        'max-kg' => ['Peso maximo', 'kg', 'fa-solid fa-dumbbell'],
        'max-reps' => ['Repeticiones maximas', 'reps', 'fa-solid fa-repeat'],
    ];

    const measurementMetricList = ['waist', 'hip', 'chest', 'arm', 'thigh', 'calf', 'neck', 'body-fat'];

    const recordMetricList = ['max-kg', 'max-reps'];

    static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    static function formatValue(string $metric, float $value): string
    {
        return self::formatNumber($value).' '.(self::metricMap[$metric][1] ?? '');
    }

    static function label(string $metric): string
    {
        return self::metricMap[$metric][0] ?? $metric;
    }

    static function icon(string $metric): string
    {
        return self::metricMap[$metric][2] ?? 'fa-solid fa-chart-line';
    }

    // no siempre bajar es mejor (ej. brazo): el peso y las medidas muestran la diferencia sin juzgarla
    static function changeText(string $metric, float $value, ?float $previousValue): ?string
    {
        if($previousValue === null){
            return null;
        }
        $change = round($value - $previousValue, 2);
        if($change == 0){
            return 'sin cambios';
        }
        return ($change > 0 ? '+' : '−').self::formatValue($metric, abs($change));
    }

    // texto del registro para el calendario y el historial
    static function entryText(array $entry): string
    {
        $value = self::formatValue($entry['metric'], (float)$entry['value']);
        $exerciseName = '«'.($entry['exerciseName'] ?? 'ejercicio').'»';
        return match (true) {
            $entry['metric'] === 'body-weight' => 'Registraste tu peso: '.$value,
            $entry['metric'] === 'max-kg' && $entry['isPersonalBest'] => 'Nuevo record en '.$exerciseName.': '.$value,
            $entry['metric'] === 'max-reps' && $entry['isPersonalBest'] => 'Nuevo record de repeticiones en '.$exerciseName.': '.$value,
            $entry['metric'] === 'max-kg' => 'Levantaste '.$value.' en '.$exerciseName,
            $entry['metric'] === 'max-reps' => 'Hiciste '.$value.' en '.$exerciseName,
            default => self::label($entry['metric']).': '.$value,
        };
    }

    static function buildEntryItem(array $entry): array
    {
        $change = in_array($entry['metric'], self::recordMetricList, true)
            ? null
            : self::changeText($entry['metric'], (float)$entry['value'], $entry['previousValue'] !== null ? (float)$entry['previousValue'] : null);
        return [
            'id' => $entry['id'],
            'icon' => $entry['isPersonalBest'] ? 'fa-solid fa-trophy' : self::icon($entry['metric']),
            'text' => self::entryText($entry),
            'changeText' => $change,
            'note' => $entry['note'] ?? null,
            'dateLabel' => Carbon::parse($entry['entryOn'])->format('d/m/Y'),
            'isPersonalBest' => (bool)$entry['isPersonalBest'],
        ];
    }

    // indice de masa corporal (OMS): peso / altura²
    static function bmi(?float $weightKg, ?int $heightCm): ?array
    {
        if(!$weightKg || !$heightCm){
            return null;
        }
        $bmi = round($weightKg / (($heightCm / 100) ** 2), 1);
        [$label, $level] = match (true) {
            $bmi < 18.5 => ['Bajo peso', 'low'],
            $bmi < 25 => ['Peso saludable', 'ok'],
            $bmi < 30 => ['Sobrepeso', 'high'],
            default => ['Obesidad', 'high'],
        };
        return ['value' => self::formatNumber($bmi), 'label' => $label, 'level' => $level];
    }
}
