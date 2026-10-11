<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ConfigController extends Controller
{
    /**
     * Apartados de configuracion de la app. Para sumar uno nuevo basta con agregarlo aca;
     * route queda en null hasta que el apartado tenga su propia pantalla.
     */
    private const sectionList = [
        [
            'key' => 'resources',
            'title' => 'Proveedores de recursos',
            'description' => 'Donde se guardan las imagenes y videos de musculos, ejercicios, gimnasios y perfiles.',
            'icon' => 'fa-solid fa-cloud-arrow-up',
            'route' => null,
        ],
        [
            'key' => 'mail',
            'title' => 'Correo',
            'description' => 'Servidor y remitente de los correos que envia la app.',
            'icon' => 'fa-solid fa-envelope',
            'route' => null,
        ],
        [
            'key' => 'messaging',
            'title' => 'Mensajeria',
            'description' => 'Canales para avisar a los usuarios: WhatsApp, SMS y notificaciones.',
            'icon' => 'fa-solid fa-comment-dots',
            'route' => null,
        ],
    ];

    public function index(): View{
        $data['sections'] = self::sectionList;
        return $this->renderView('config.index', compact('data'));
    }
}
