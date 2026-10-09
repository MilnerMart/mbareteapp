<?php

namespace App\Support;

/**
 * Textos de las solicitudes (tickets) que devuelve el backend como slugs.
 */
class TicketLabels
{
    private const typeLabelMap = [
        'trainer-request' => 'Alta como entrenador',
        'gym-join' => 'Ingreso a gimnasio',
    ];

    private const stateLabelMap = [
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
    ];

    static function typeLabel(?string $type): string
    {
        return self::typeLabelMap[$type] ?? 'Solicitud';
    }

    static function stateLabel(?string $state): string
    {
        return self::stateLabelMap[$state] ?? '-';
    }

    /**
     * Texto que ve quien hizo la solicitud en su perfil.
     */
    static function requesterSummary(array $ticket): string
    {
        $gymName = $ticket['refs']['gym']['name'] ?? 'el gimnasio';
        return match ($ticket['type'] ?? null) {
            'trainer-request' => match ($ticket['state'] ?? null) {
                'approved' => 'Ya eres entrenador',
                'rejected' => 'Tu solicitud para ser entrenador fue rechazada',
                default => 'Pendiente de revision para ser entrenador',
            },
            'gym-join' => match ($ticket['state'] ?? null) {
                'approved' => 'Aceptado en '.$gymName,
                'rejected' => 'No fuiste aceptado en '.$gymName,
                default => 'Pendiente de aceptar en '.$gymName,
            },
            default => self::stateLabel($ticket['state'] ?? null),
        };
    }

    static function historyActionLabel(?string $action): string
    {
        return match ($action) {
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'resubmitted' => 'Reenviada',
            default => '-',
        };
    }

    /**
     * El backend ya devuelve las fechas en hora de Paraguay (BaseHelper::toDisplayDate), aca solo se formatean.
     */
    static function formatDate(?string $isoDate): string
    {
        return $isoDate ? \Carbon\Carbon::parse($isoDate)->format('d/m/Y H:i') : '-';
    }
}
