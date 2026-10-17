<?php

namespace App\Support;

/**
 * Lectura de los permisos del usuario logueado guardados en sesion.
 * Solo sirve para decidir que mostrar, el backend es quien valida.
 */
class AuthPermits
{
    const seeAllPermitSlug = 'the-one-who-sees-all', createGymEntityPermitSlug = 'create-gym-entity', assignRoutinesPermitSlug = 'Assing-routines',
        belongsToGymPermitSlug = 'belongs-to-gym', createCatalogEntityPermitSlug = 'create-catalog-entity',
        allowConfigPermitSlug = 'allow-config', deactivateUserPermitSlug = 'deactivate-user',
        seeUserDetailPermitSlug = 'see-user-detail';

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

    /**
     * Proponer musculos y ejercicios (el admin los publica directo).
     */
    static function canCreateCatalog(): bool
    {
        return self::hasPermit(self::createCatalogEntityPermitSlug);
    }

    static function canAssignRoutines(): bool
    {
        return self::hasPermit(self::assignRoutinesPermitSlug);
    }

    /**
     * Ver y cambiar la configuracion de la app (proveedores, correo, mensajeria...).
     */
    static function canConfigureApp(): bool
    {
        return self::hasPermit(self::allowConfigPermitSlug);
    }

    /**
     * Abrir la ficha de un usuario (desde la lista de alumnos del gimnasio).
     */
    static function canSeeUserDetail(): bool
    {
        return self::hasPermit(self::seeUserDetailPermitSlug);
    }

    /**
     * Dar de baja a un usuario: se hace desde su ficha, por eso pide los dos permisos.
     */
    static function canDeactivateUsers(): bool
    {
        return self::canSeeUserDetail() && self::hasPermit(self::deactivateUserPermitSlug);
    }
}
