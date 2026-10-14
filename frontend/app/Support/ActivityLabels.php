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

    private const actionVerbMap = [
        'created' => 'Creo',
        'updated' => 'Modifico',
        'deleted' => 'Elimino',
    ];

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

    static function buildItem(array $entityLog): array
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
            $model === 'user' => self::userText($action, $data),
            $model === 'ticket' && $action === 'created' => 'Envio '.$subject,
            $action === 'approved' => 'Aprobo '.$subject.$requesterSuffix,
            $action === 'rejected' => 'Rechazo '.$subject.$requesterSuffix,
            $action === 'resubmitted' => 'Reenvio '.$subject,
            $action === 'exercise-added' => 'Agrego el ejercicio «'.($data['exerciseName'] ?? '-').'» a '.$subject,
            $action === 'exercise-removed' => 'Quito el ejercicio «'.($data['exerciseName'] ?? '-').'» de '.$subject,
            $action === 'assigned' => 'Asigno '.$subject.' a '.($data['targetUserName'] ?? 'un alumno'),
            $action === 'unassigned' => 'Le quito '.$subject.' a '.($data['targetUserName'] ?? 'un alumno'),
            $action === 'resource-added' => 'Agrego una imagen publica a '.$subject,
            $action === 'resource-removed' => 'Quito una imagen publica de '.$subject,
            $action === 'image-updated' => 'Cambio la imagen de '.$subject,
            $action === 'member-removed' => 'Quito a '.($data['targetUserName'] ?? 'un alumno').' de '.$subject,
            $action === 'visibility-changed' => ($data['isPublic'] ?? false)
                ? 'Se mostro en la lista de alumnos de '.$subject
                : 'Se oculto de la lista de alumnos de '.$subject,
            default => (self::actionVerbMap[$action] ?? 'Actualizo').' '.$subject,
        };

        $note = match (true) {
            ($data['pendingReview'] ?? false) => 'Quedo pendiente de aprobacion',
            isset($data['note']) => '“'.$data['note'].'”',
            default => null,
        };

        return [
            'icon' => $action === 'rank-up' ? 'fa-solid fa-medal' : (self::modelIconMap[$model] ?? 'fa-solid fa-clock-rotate-left'),
            'text' => $text,
            'note' => $note,
            'isDelete' => in_array($action, ['deleted', 'rejected'], true),
            'isHighlight' => $action === 'rank-up',
        ];
    }

    private static function userText(?string $action, array $data): string
    {
        return match ($action) {
            'rank-up' => 'Subio al rango '.ProfileLabels::rankLabel($data['rank'] ?? null, $data['gender'] ?? null),
            'image-updated' => 'Cambio su foto de perfil',
            default => 'Actualizo su perfil'.(empty($data['fields']) ? '' : ': '.implode(', ', array_map(
                fn(string $field) => ProfileLabels::profileFieldLabelMap[$field] ?? $field, $data['fields']))),
        };
    }
}
