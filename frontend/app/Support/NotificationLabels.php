<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Textos y enlaces de la campanita a partir de los avisos que devuelve el backend (type + data).
 */
class NotificationLabels
{
    static function buildPanel(?array $inbox): array
    {
        $itemList = array_map(fn(array $notification) => self::buildItem($notification), $inbox['items'] ?? []);
        $profileItem = self::buildPendingProfileItem($inbox['pendingProfileFields'] ?? []);
        if($profileItem){
            array_unshift($itemList, $profileItem);
        }
        $unreadCount = (int)($inbox['unreadCount'] ?? 0) + ($profileItem ? 1 : 0);

        return [
            'items' => $itemList,
            'unreadCount' => $unreadCount,
            'badgeText' => $unreadCount > 9 ? '9+' : (string)$unreadCount,
            'hasUnreadNotifications' => (int)($inbox['unreadCount'] ?? 0) > 0,
        ];
    }

    static function buildItem(array $notification): array
    {
        $data = $notification['data'] ?? [];
        $typeLabel = TicketLabels::typeLabel($data['ticketType'] ?? null);
        [$icon, $text, $detailList] = match ($notification['type'] ?? null) {
            'ticket-received' => [
                'fa-solid fa-ticket',
                'Tienes un nuevo ticket de '.mb_strtolower($typeLabel),
                [$data['requesterName'] ?? null, $data['gymName'] ?? null, $data['ticketNumber'] ?? null],
            ],
            'ticket-resubmitted' => [
                'fa-solid fa-rotate-right',
                ($data['requesterName'] ?? 'Un usuario').' reenvio su ticket de '.mb_strtolower($typeLabel),
                [$data['gymName'] ?? null, $data['ticketNumber'] ?? null],
            ],
            'ticket-resolved' => [
                ($data['ticketState'] ?? null) === 'approved' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark',
                'Tu ticket de '.mb_strtolower($typeLabel).' fue '.(($data['ticketState'] ?? null) === 'approved' ? 'aprobado' : 'rechazado'),
                [$data['gymName'] ?? null, isset($data['resolverName']) ? 'por '.$data['resolverName'] : null, $data['ticketNumber'] ?? null],
            ],
            'routine-assigned' => [
                'fa-solid fa-clipboard-list',
                'Te asignaron la rutina '.($data['routineName'] ?? ''),
                [isset($data['assignerName']) ? 'por '.$data['assignerName'] : null],
            ],
            'reminder' => [
                'fa-solid fa-calendar-day',
                'Recordatorio: '.($data['title'] ?? ''),
                [match ($data['daysLeft'] ?? null) {
                    0 => 'Es hoy',
                    1 => 'Es mañana',
                    2 => 'Es pasado mañana',
                    default => null,
                }, isset($data['noteOn']) ? Carbon::parse($data['noteOn'])->format('d/m/Y') : null],
            ],
            default => ['fa-solid fa-bell', 'Tienes un aviso nuevo', []],
        };

        return [
            'icon' => $icon,
            'text' => trim($text),
            'detail' => implode(' · ', array_filter($detailList)),
            'timeLabel' => isset($notification['createdAt']) ? Carbon::parse($notification['createdAt'])->locale('es')->diffForHumans() : null,
            'isRead' => (bool)($notification['isRead'] ?? false),
            'url' => route('notification.open', $notification['id']),
        ];
    }

    private static function buildPendingProfileItem(array $fieldList): ?array
    {
        $labelList = array_filter(array_map(fn(string $field) => ProfileLabels::profileFieldLabelMap[$field] ?? null, $fieldList));
        if(!$labelList){
            return null;
        }
        return [
            'icon' => 'fa-solid fa-user-pen',
            'text' => 'Completa los datos de tu perfil',
            'detail' => 'Falta: '.implode(', ', $labelList),
            'timeLabel' => null,
            'isRead' => false,
            'url' => route('user.profile', ['id' => session('auth_user.id'), 'edit' => 1]),
        ];
    }

    // a donde lleva el aviso al abrirlo
    static function targetUrl(array $notification): ?string
    {
        $refId = $notification['refId'] ?? null;
        return match ($notification['type'] ?? null) {
            'ticket-received', 'ticket-resubmitted' => route('ticket.index'),
            'ticket-resolved' => $refId ? route('ticket.show', $refId) : null,
            'routine-assigned' => $refId ? route('routine.show', $refId) : null,
            'reminder' => isset($notification['data']['noteOn'])
                ? CalendarMonth::dayUrl(CarbonImmutable::parse($notification['data']['noteOn'], CalendarMonth::timezone))
                : route('calendar.index'),
            default => null,
        };
    }
}
