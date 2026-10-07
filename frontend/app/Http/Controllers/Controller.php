<?php

namespace App\Http\Controllers;

use App\Support\AuthPermits;
use Illuminate\View\View;

abstract class Controller
{
    /**
     * Renderiza la vista con los permisos que necesita el menu del layout.
     */
    protected function renderView(string $view, array $data = []): View
    {
        return view($view, $data + [
            'canManageGyms' => AuthPermits::canManageGyms(),
        ]);
    }

    protected function apiErrorMessage(?array $response, string $fallback): string
    {
        if(isset($response['error']['message'])){
            return $response['error']['message'];
        }

        if(isset($response['message'])){
            return $response['message'];
        }

        $firstError = $response['errors'] ?? null;

        if(is_array($firstError)){
            $fieldErrors = reset($firstError);
            if(is_array($fieldErrors)){
                return $fieldErrors[0] ?? $fallback;
            }
        }

        return $fallback;
    }

    protected function isApiSuccess(?array $response): bool
    {
        return (bool) ($response['success'] ?? false);
    }
}
