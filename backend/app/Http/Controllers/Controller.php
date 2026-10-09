<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Db\DbConnector;
use App\Models\Permit;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\Request;

abstract class Controller
{
    use ApiResponse;

    private DbConnector $dbConnector;

    public function __construct( DbConnector $dbConnector ) {
        $this->dbConnector = $dbConnector;
    }

    function getDbConnector():DbConnector {
        return $this->dbConnector;
    }

    /**
     * Usuario logueado en rutas publicas (catalogo): null si no mando token.
     */
    protected function queryViewer(Request $request): ?User {
        return $request->user('sanctum');
    }

    protected function isAdminViewer(?User $viewer): bool {
        return (bool)$viewer?->hasSeeAllPermit($this->dbConnector);
    }

    protected function createCatalogPermitOrFail(?User $user): User {
        if(!$user?->hasPermit($this->dbConnector, Permit::createCatalogEntityPermitSlug)){
            throw PublicException::forbiddenError('No tienes permisos para proponer musculos o ejercicios');
        }
        return $user;
    }

    protected function seeAllPermitOrFail(?User $user): void {
        if(!$user?->hasSeeAllPermit($this->dbConnector)){
            throw PublicException::forbiddenError('Solo el administrador puede realizar esta accion');
        }
    }
}
