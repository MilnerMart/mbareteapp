<?php

namespace App;


class PublicException extends BackendException{
    public static function validationError(string $message,string $infoCode = 'validation_error',array $data = []): self {
        return new self([
            'text' => $message,
            'infoCode' => $infoCode,
            'httpCode' => 400,
            'data' => $data,
        ]);
    }

    public static function unauthorizedError(string $message = 'No autorizado',string $infoCode = 'unauthorized',array $data = []): self {
        return new self([
            'text' => $message,
            'infoCode' => $infoCode,
            'httpCode' => 401,
            'data' => $data,
        ]);
    }

    public static function internalError(string $message = 'Error interno del servidor',string $infoCode = 'internal_error',array $data = []): self {
        return new self([
            'text' => $message,
            'infoCode' => $infoCode,
            'httpCode' => 500,
            'data' => $data,
        ]);
    }
}