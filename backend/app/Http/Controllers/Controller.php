<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Db\DbConnector;
use App\Models\User;
use App\PublicException;

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

    protected function seeAllPermitOrFail(?User $user): void {
        if(!$user?->hasSeeAllPermit($this->dbConnector)){
            throw PublicException::forbiddenError('Solo el administrador puede realizar esta accion');
        }
    }
}
