<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Textos e iconos de los logros; el backend solo manda el slug, la meta y cuanto lleva (App\Models\Achievement).
 */
class AchievementLabels
{
    const groupLabelMap = [
        'bestStreak' => 'Constancia',
        'trainingDays' => 'Dias entrenados',
        'kilos' => 'Kilos levantados',
        'minutes' => 'Tiempo entrenado',
    ];

    const catalog = [
        'streak-3' => ['Arrancaste con todo', '3 dias seguidos entrenando. ¡Esto recien empieza!', 'fa-solid fa-fire'],
        'streak-7' => ['Semana de hierro', 'Una semana entera sin aflojar.', 'fa-solid fa-fire-flame-curved'],
        'streak-14' => ['Disciplina de acero', 'Dos semanas seguidas. Ya es un habito.', 'fa-solid fa-shield-halved'],
        'streak-30' => ['Maquina constante', 'Un mes entero entrenando sin cortar. ¡Sos una maquina!', 'fa-solid fa-gears'],
        'streak-60' => ['Imparable', '60 dias seguidos. Nada te frena.', 'fa-solid fa-bolt'],
        'streak-100' => ['Leyenda de la constancia', '100 dias seguidos. Entraste en la historia del gimnasio.', 'fa-solid fa-crown'],
        'days-1' => ['Primer paso', 'Completaste tu primer dia de entrenamiento.', 'fa-solid fa-shoe-prints'],
        'days-10' => ['Tomandole el gusto', '10 dias entrenados.', 'fa-solid fa-seedling'],
        'days-25' => ['Habitue del gym', '25 dias entrenados. Ya te conocen en la recepcion.', 'fa-solid fa-id-card'],
        'days-50' => ['Medio centenar', '50 dias entrenados.', 'fa-solid fa-medal'],
        'days-100' => ['Centenario', '100 dias entrenados. ¡Que nivel!', 'fa-solid fa-award'],
        'days-200' => ['Vivis en el gym', '200 dias entrenados. ¿Tenes cama ahi?', 'fa-solid fa-house'],
        'kilos-500' => ['Mudanza de piano', 'Levantaste 500 kg en total, lo que pesa un piano.', 'fa-solid fa-music'],
        'kilos-1500' => ['Grua humana', '1.500 kg en total: ya podes alzar un auto.', 'fa-solid fa-car-side'],
        'kilos-3000' => ['Fuerza de hipopotamo', '3.000 kg, lo que pesa un hipopotamo.', 'fa-solid fa-hippo'],
        'kilos-15000' => ['Superhombre', '15.000 kg en total. ¡Ahora podes alzar el peso de un bondi!', 'fa-solid fa-bus'],
        'kilos-40000' => ['Despegue', '40.000 kg: el peso de un avion de pasajeros.', 'fa-solid fa-plane'],
        'kilos-150000' => ['Titan', '150.000 kg, lo mismo que una ballena azul.', 'fa-solid fa-fish'],
        'minutes-60' => ['Primera hora', 'Sumaste tu primera hora de entrenamiento.', 'fa-solid fa-hourglass-start'],
        'minutes-600' => ['Diez horas de sudor', '10 horas entrenadas.', 'fa-solid fa-hourglass-half'],
        'minutes-3000' => ['Maratonista del gym', '50 horas entrenadas.', 'fa-solid fa-stopwatch'],
        'minutes-6000' => ['Cien horas', '100 horas dedicadas a vos.', 'fa-solid fa-clock'],
    ];

    static function title(?string $slug): string
    {
        return self::catalog[$slug][0] ?? 'Logro';
    }

    static function message(?string $slug): string
    {
        return self::catalog[$slug][1] ?? '';
    }

    static function icon(?string $slug): string
    {
        return self::catalog[$slug][2] ?? 'fa-solid fa-trophy';
    }

    static function buildPanel(array $achievementList, bool $isOwnProfile): array
    {
        $groupList = [];
        foreach ($achievementList as $achievement) {
            $groupList[$achievement['metric']]['title'] = self::groupLabelMap[$achievement['metric']] ?? 'Otros';
            $groupList[$achievement['metric']]['items'][] = [
                'title' => self::title($achievement['slug']),
                'message' => self::message($achievement['slug']),
                'icon' => self::icon($achievement['slug']),
                'isUnlocked' => $achievement['isUnlocked'],
                'dateLabel' => $achievement['unlockedAt'] ? 'Lo lograste el '.Carbon::parse($achievement['unlockedAt'])->format('d/m/Y') : null,
                'progressText' => $achievement['isUnlocked'] ? null : self::formatValue($achievement['metric'], $achievement['current'])
                    .' de '.self::formatValue($achievement['metric'], $achievement['goal']),
                'progressPercent' => $achievement['goal'] ? (int)floor(100 * $achievement['current'] / $achievement['goal']) : 0,
            ];
        }
        $unlockedCount = count(array_filter($achievementList, fn(array $achievement) => $achievement['isUnlocked']));
        return [
            'unlockedCount' => $unlockedCount,
            'totalCount' => count($achievementList),
            'groupList' => array_values($groupList),
            'emptyText' => $isOwnProfile ? 'Entrena y marca tus ejercicios para desbloquear logros.' : 'Todavia no desbloqueo logros.',
        ];
    }

    private static function formatValue(string $metric, int $value): string
    {
        return match ($metric) {
            'kilos' => number_format($value, 0, ',', '.').' kg',
            'minutes' => ProfileLabels::formatMinutes($value),
            default => $value.' dia'.($value === 1 ? '' : 's'),
        };
    }
}
