<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Textos de las reglas del sistema (tareas que corren solas). El backend manda el slug de cada regla, su horario
 * y sus ultimas ejecuciones con el resumen de lo que hicieron.
 */
class SystemRuleLabels
{
    // slug => textos de la regla y como se lee su resumen: clave => [singular, plural]
    const ruleMap = [
        'reminders' => [
            'title' => 'Recordatorios del calendario',
            'description' => 'Avisa en la campanita las anotaciones del calendario cuyo recordatorio ya llego (2 dias antes, el dia anterior o el mismo dia).',
            'icon' => 'fa-solid fa-calendar-day',
            'summary' => [
                'notes' => ['anotacion avisada', 'anotaciones avisadas'],
            ],
        ],
        'ranking' => [
            'title' => 'Ranking de temporadas',
            'description' => 'Cierra las temporadas que terminaron (podio, premios, avisos y actividad) y avisa a los alumnos cuando empieza una temporada programada.',
            'icon' => 'fa-solid fa-ranking-star',
            'summary' => [
                'gyms' => ['gimnasio revisado', 'gimnasios revisados'],
                'closedSeasons' => ['temporada cerrada', 'temporadas cerradas'],
                'startNotices' => ['aviso de inicio', 'avisos de inicio'],
            ],
        ],
    ];

    const frequencyMap = [
        'daily' => 'Todos los dias',
        'hourly' => 'Cada hora',
    ];

    // estado => [texto, clase]
    const statusMap = [
        'pending' => ['Pendiente', 'is-pending'],
        'running' => ['En curso', 'is-running'],
        'completed' => ['Completada', 'is-completed'],
        'failed' => ['Fallida', 'is-failed'],
        'skipped' => ['Omitida', 'is-skipped'],
    ];

    static function buildRuleList(array $ruleList): array
    {
        return array_map(function(array $rule) {
            $key = 'rules.'.$rule['ruleModelId'];
            $texts = self::ruleMap[$rule['slug']] ?? ['title' => $rule['slug'], 'description' => '', 'icon' => 'fa-solid fa-gear'];
            $isEnabled = (bool)old($key.'.is_enabled', $rule['isEnabled']);
            $frequency = old($key.'.frequency', $rule['frequency']);
            $runAt = old($key.'.run_at', $rule['runAt']);
            $retryMinutes = (int)old($key.'.retry_minutes', $rule['retryMinutes'] ?? 0);
            return [
                'ruleModelId' => $rule['ruleModelId'],
                'title' => $texts['title'],
                'description' => $texts['description'],
                'icon' => $texts['icon'],
                'isEnabled' => $isEnabled,
                'frequency' => $frequency,
                'runAt' => $runAt,
                'retryMinutes' => $retryMinutes,
                'retryText' => $retryMinutes > 0
                    ? 'Si falla, reintenta a los '.$retryMinutes.' minutos (sin pasarse de su proximo horario).'
                    : 'Si falla, no reintenta: espera su proximo horario.',
                'frequencyOptions' => self::frequencyMap,
                'scheduleText' => $frequency === 'hourly'
                    ? 'Cada hora en el minuto '.substr($runAt, 3, 2).' (en "Cada hora" se usa solo el minuto)'
                    : 'Todos los dias a las '.$runAt.' (hora de Paraguay)',
                'nextRunLabel' => $rule['isEnabled'] && $rule['nextRunAt']
                    ? 'Proxima: '.self::formatDate($rule['nextRunAt'])
                    : 'Desactivada: no corre sola',
                'runUrl' => route('config.rules.run', $rule['ruleModelId']),
                'runFormId' => 'runRule'.$rule['ruleModelId'],
                'runList' => array_map(fn(array $run) => self::buildRun($rule['slug'], $run), $rule['runs'] ?? []),
            ];
        }, $ruleList);
    }

    static function summaryText(string $slug, array $summary): string
    {
        $labelMap = self::ruleMap[$slug]['summary'] ?? [];
        $partList = [];
        foreach ($summary as $key => $value) {
            $partList[] = $value.' '.(isset($labelMap[$key]) ? $labelMap[$key][(int)$value === 1 ? 0 : 1] : $key);
        }
        return $partList ? implode(' · ', $partList) : 'sin cambios';
    }

    private static function buildRun(string $slug, array $run): array
    {
        [$statusLabel, $statusClass] = self::statusMap[$run['status']] ?? [$run['status'], ''];
        $startedAt = $run['startedAt'] ?? $run['createdAt'];
        return [
            'statusLabel' => $statusLabel,
            'statusClass' => $statusClass,
            'originLabel' => $run['isManual'] ? 'Manual' : 'Automatica',
            'dateLabel' => $startedAt ? self::formatDate($startedAt) : '-',
            'durationLabel' => $run['startedAt'] && $run['finishedAt']
                ? (int)round(Carbon::parse($run['startedAt'])->diffInSeconds(Carbon::parse($run['finishedAt']), true)).' s'
                : null,
            'detailText' => $run['status'] === 'completed' ? self::summaryText($slug, $run['summary'] ?? []) : ($run['error'] ?? null),
        ];
    }

    private static function formatDate(string $date): string
    {
        return Carbon::parse($date)->setTimezone(CalendarMonth::timezone)->format('d/m/Y H:i');
    }
}
