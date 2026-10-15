<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Textos del registro de actividad (EntityLog) que devuelve el backend como action + model + data.
 */
class ActivityLabels
{
    const modelFilterMap = [
        'routine' => 'Rutinas',
        'exercise' => 'Ejercicios',
        'muscle' => 'Musculos',
        'gym' => 'Gimnasios',
        'ticket' => 'Solicitudes',
        'user' => 'Perfil y nivel',
    ];

    private const modelIconMap = [
        'routine' => 'fa-solid fa-clipboard-list',
        'exercise' => 'fa-solid fa-person-running',
        'muscle' => 'fa-solid fa-dumbbell',
        'gym' => 'fa-solid fa-building',
        'ticket' => 'fa-solid fa-ticket',
        'user' => 'fa-solid fa-user',
    ];

    // articulo + nombre de la entidad: "la rutina", "el ejercicio"...
    private const modelNounMap = [
        'routine' => 'la rutina',
        'exercise' => 'el ejercicio',
        'muscle' => 'el musculo',
        'gym' => 'el gimnasio',
    ];

    // [tercera persona (lo lee el admin), segunda persona (lo lee el propio usuario en su calendario)]
    private const verbMap = [
        'created' => ['Creo', 'Creaste'],
        'updated' => ['Modifico', 'Modificaste'],
        'deleted' => ['Elimino', 'Eliminaste'],
        'sent' => ['Envio', 'Enviaste'],
        'approved' => ['Aprobo', 'Aprobaste'],
        'rejected' => ['Rechazo', 'Rechazaste'],
        'resubmitted' => ['Reenvio', 'Reenviaste'],
        'added' => ['Agrego', 'Agregaste'],
        'removed' => ['Quito', 'Quitaste'],
        'assigned' => ['Asigno', 'Asignaste'],
        'unassigned' => ['Le quito', 'Le quitaste'],
        'changed' => ['Cambio', 'Cambiaste'],
        'shown' => ['Se mostro', 'Te mostraste'],
        'hidden' => ['Se oculto', 'Te ocultaste'],
        'ranked-up' => ['Subio', 'Subiste'],
        'profile-updated' => ['Actualizo su perfil', 'Actualizaste tu perfil'],
        'photo-changed' => ['Cambio su foto de perfil', 'Cambiaste tu foto de perfil'],
        'unlocked' => ['Desbloqueo el logro', 'Desbloqueaste el logro'],
        'season-started' => ['Inicio', 'Iniciaste'],
        'season-dates' => ['Cambio las fechas de', 'Cambiaste las fechas de'],
        'season-finished' => ['Termino', 'Terminaste'],
        'pending' => ['Quedo pendiente de aprobacion', 'Quedo pendiente de aprobacion'],
    ];

    private static function verb(string $key, bool $isOwn): string
    {
        return self::verbMap[$key][$isOwn ? 1 : 0];
    }

    /**
     * Agrupa los registros por dia: [['dateLabel' => '13/10/2026', 'items' => [...]], ...]
     */
    static function buildDayList(array $entityLogList): array
    {
        $dayList = [];
        foreach ($entityLogList as $entityLog) {
            $date = isset($entityLog['createdAt']) ? Carbon::parse($entityLog['createdAt']) : null;
            $dateLabel = $date ? $date->format('d/m/Y') : '-';
            $dayList[$dateLabel]['dateLabel'] = $dateLabel;
            $dayList[$dateLabel]['items'][] = self::buildItem($entityLog) + ['timeLabel' => $date?->format('H:i')];
        }
        return array_values($dayList);
    }

    // $isOwn: el texto le habla al propio usuario ("Creaste...") en vez de describirlo ("Creo...")
    static function buildItem(array $entityLog, bool $isOwn = false): array
    {
        $data = $entityLog['data'] ?? [];
        $model = $entityLog['model'] ?? null;
        $action = $entityLog['action'] ?? null;
        $subject = (self::modelNounMap[$model] ?? 'el registro').' «'.($data['name'] ?? '#'.($entityLog['entityId'] ?? '')).'»';
        if($model === 'ticket'){
            $subject = 'la solicitud '.($data['name'] ?? '').' ('.TicketLabels::typeLabel($data['ticketType'] ?? null).')';
        }
        $requesterSuffix = isset($data['requesterName']) ? ' de '.$data['requesterName'] : '';

        $text = match (true) {
            $model === 'user' => self::userText($action, $data, $isOwn),
            $model === 'ticket' && $action === 'created' => self::verb('sent', $isOwn).' '.$subject,
            $action === 'approved' => self::verb('approved', $isOwn).' '.$subject.$requesterSuffix,
            $action === 'rejected' => self::verb('rejected', $isOwn).' '.$subject.$requesterSuffix,
            $action === 'resubmitted' => self::verb('resubmitted', $isOwn).' '.$subject,
            $action === 'exercise-added' => self::verb('added', $isOwn).' el ejercicio «'.($data['exerciseName'] ?? '-').'» a '.$subject,
            $action === 'exercise-removed' => self::verb('removed', $isOwn).' el ejercicio «'.($data['exerciseName'] ?? '-').'» de '.$subject,
            $action === 'assigned' => self::verb('assigned', $isOwn).' '.$subject.' a '.($data['targetUserName'] ?? 'un alumno'),
            $action === 'unassigned' => self::verb('unassigned', $isOwn).' '.$subject.' a '.($data['targetUserName'] ?? 'un alumno'),
            $action === 'resource-added' => self::verb('added', $isOwn).' una imagen publica a '.$subject,
            $action === 'resource-removed' => self::verb('removed', $isOwn).' una imagen publica de '.$subject,
            $action === 'image-updated' => self::verb('changed', $isOwn).' la imagen de '.$subject,
            $action === 'member-removed' => self::verb('removed', $isOwn).' a '.($data['targetUserName'] ?? 'un alumno').' de '.$subject,
            $action === 'visibility-changed' => ($data['isPublic'] ?? false)
                ? self::verb('shown', $isOwn).' en la lista de alumnos de '.$subject
                : self::verb('hidden', $isOwn).' de la lista de alumnos de '.$subject,
            $action === 'season-started' => self::verb('season-started', $isOwn).' '.self::seasonText($data).self::seasonRangeText($data),
            $action === 'season-updated' => self::verb('season-dates', $isOwn).' '.self::seasonText($data).self::seasonRangeText($data),
            $action === 'season-result' => ($data['isOwner'] ?? false)
                ? 'Termino '.self::seasonText($data).' con '.($data['participantCount'] ?? 0).' participantes'
                : self::verb('season-finished', $isOwn).' en el puesto '.($data['position'] ?? '-').' '.self::seasonText($data),
            default => self::verb(isset(self::verbMap[$action]) ? $action : 'updated', $isOwn).' '.$subject,
        };

        $note = match (true) {
            ($data['pendingReview'] ?? false) => self::verb('pending', $isOwn),
            $action === 'season-result' && isset($data['points']) => RankingLabels::formatScore((int)$data['points'])
                .(empty($data['awards']) ? '' : ' · Premios: '.implode(', ', array_map(fn(string $slug) => RankingLabels::awardTitle($slug), $data['awards']))),
            isset($data['note']) => '“'.$data['note'].'”',
            default => null,
        };

        return [
            'icon' => match ($action) {
                'rank-up' => 'fa-solid fa-medal',
                'achievement' => AchievementLabels::icon($data['achievement'] ?? null, $data['gender'] ?? null),
                'season-started' => 'fa-solid fa-flag-checkered',
                'season-updated' => 'fa-solid fa-calendar-days',
                'season-result' => ($data['position'] ?? 99) <= 3 ? 'fa-solid fa-medal' : 'fa-solid fa-ranking-star',
                default => self::modelIconMap[$model] ?? 'fa-solid fa-clock-rotate-left',
            },
            'text' => $text,
            'note' => $note,
            'isDelete' => in_array($action, ['deleted', 'rejected'], true),
            'isHighlight' => in_array($action, ['rank-up', 'achievement', 'season-result'], true),
        ];
    }

    private static function seasonText(array $data): string
    {
        return 'la temporada '.($data['season'] ?? '').' del gimnasio «'.($data['name'] ?? '').'»';
    }

    private static function seasonRangeText(array $data): string
    {
        return isset($data['startsOn'], $data['endsOn'])
            ? ' (del '.Carbon::parse($data['startsOn'])->format('d/m').' al '.Carbon::parse($data['endsOn'])->format('d/m/Y').')'
            : '';
    }

    private static function userText(?string $action, array $data, bool $isOwn): string
    {
        return match ($action) {
            'achievement' => self::verb('unlocked', $isOwn).' «'.AchievementLabels::title($data['achievement'] ?? null, $data['gender'] ?? null).'»',
            'rank-up' => self::verb('ranked-up', $isOwn).' al rango '.ProfileLabels::rankLabel($data['rank'] ?? null, $data['gender'] ?? null),
            'image-updated' => self::verb('photo-changed', $isOwn),
            default => self::verb('profile-updated', $isOwn).(empty($data['fields']) ? '' : ': '.implode(', ', array_map(
                fn(string $field) => ProfileLabels::profileFieldLabelMap[$field] ?? $field, $data['fields']))),
        };
    }
}
