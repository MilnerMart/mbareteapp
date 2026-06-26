<?php

namespace App;

use Exception;

trait PublicException
{
    public function validationError(string $message, int $code = 400): void{
        throw new Exception($message, $code);
    }

    public function notFoundError(string $message = 'Recurso no encontrado'): void{
        throw new Exception($message, 404);
    }

    public function unauthorizedError(string $message = 'No autorizado'): void{
        throw new Exception($message, 401);
    }
}