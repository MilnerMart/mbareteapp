<?php

namespace App\Support;

/**
 * Lectura de los permisos del usuario logueado guardados en sesion.
 * Solo sirve para decidir que mostrar, el backend es quien valida.
 */
class AuthPermits
{
    const seeAllPermitSlug = 'the-one-who-sees-all', createGymEntityPermitSlug = 'create-gym-entity', assignRoutinesPermitSlug = 'Assing-routines',
        belongsToGymPermitSlug = 'belongs-to-gym';

    static function hasPermit(string $permitSlug): bool
    {
        $permitList = session('auth_user.permits', []);
        return in_array(self::seeAllPermitSlug, $permitList, true) || in_array($permitSlug, $permitList, true);
    }

    static function isAdmin(): bool
    {
        return self::hasPermit(self::seeAllPermitSlug);
    }

    static function canManageGyms(): bool
    {
        return self::hasPermit(self::createGymEntityPermitSlug);
    }

    static function canBelongToGym(): bool
    {
        return self::hasPermit(self::belongsToGymPermitSlug);
    }

    static function canAssignRoutines(): bool
    {
        return self::hasPermit(self::assignRoutinesPermitSlug);
    }
}
