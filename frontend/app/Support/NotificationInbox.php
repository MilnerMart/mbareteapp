<?php

namespace App\Support;

use App\Services\ApiClientService;

/**
 * Avisos de la campanita guardados en sesion: no se piden al backend en cada pantalla, solo al vencer,
 * al marcarlos como leidos (la respuesta trae la bandeja nueva) o cuando algo cambia los datos pendientes.
 */
class NotificationInbox
{
    const sessionKey = 'notification_inbox';

    // cada cuanto se buscan avisos nuevos que hayan generado otros usuarios
    const refreshSeconds = 300;

    static function get(): ?array
    {
        $cached = session(self::sessionKey);
        if($cached && $cached['expiresAt'] > now()->timestamp){
            return $cached['inbox'];
        }
        $inbox = app(ApiClientService::class)->getNotifications();
        if($inbox !== null){
            self::store($inbox);
        }
        return $inbox;
    }

    static function store(array $inbox): void
    {
        session()->put(self::sessionKey, ['inbox' => $inbox, 'expiresAt' => now()->addSeconds(self::refreshSeconds)->timestamp]);
    }

    static function forget(): void
    {
        session()->forget(self::sessionKey);
    }
}
