<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve las imagenes subidas al backend desde el dominio del front. Asi el navegador nunca
 * le pide imagenes al backend directo (funciona igual en localhost, la red local o un tunel).
 * Las imagenes propias del front se sirven como archivo estatico y no pasan por aca.
 */
class MediaController extends Controller
{
    private const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function show(string $path): Response
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if(str_contains($path, '..') || !in_array($extension, self::allowedExtensions, true)){
            abort(404);
        }

        $backendResponse = Http::get(self::backendOrigin().'/images/'.$path);
        if(!$backendResponse->successful()){
            abort(404);
        }

        return response($backendResponse->body(), 200, [
            'Content-Type' => $backendResponse->header('Content-Type') ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Esquema + host + puerto del backend, sacado de BACKEND_API_URL.
     */
    static function backendOrigin(): string
    {
        $parts = parse_url(config('services.backend.url'));
        $origin = $parts['scheme'].'://'.$parts['host'];
        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }
}
