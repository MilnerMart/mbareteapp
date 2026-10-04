<?php

namespace App;

use Exception;
use Throwable;

abstract class BackendException extends Exception {
    protected array $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = array_merge([
            'text' => 'Error interno',
            'infoCode' => null,
            'httpCode' => 500,
            'data' => null,
        ], $attributes);

        $previous = $attributes['exception'] ?? null;

        parent::__construct(
            $this->attributes['text'],
            0,
            $previous instanceof Throwable ? $previous : null
        );
    }

    public function getText(): string
    {
        return $this->attributes['text'];
    }

    public function getInfoCode(): ?string
    {
        return $this->attributes['infoCode'];
    }

    public function getHttpCode(): int
    {
        return $this->attributes['httpCode'];
    }

    public function getData(): mixed
    {
        return $this->attributes['data'];
    }

    public function getInfoArray(): array
    {
        $response = [
            'code' => $this->getInfoCode(),
            'message' => $this->getText(),
        ];

        if ($this->getData() !== null) {
            $response['data'] = $this->getData();
        }

        return $response;
    }
}