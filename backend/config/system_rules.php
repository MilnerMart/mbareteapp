<?php

use App\SystemRules\RankingSystemRule;
use App\SystemRules\ReminderSystemRule;

return [

    /*
    | Reglas que corren solas con "php artisan rules:run" (el scheduler lo llama cada minuto). Cada una extiende
    | App\SystemRules\SystemRule; al sumar una aca aparece en Configuracion con su horario por defecto.
    */
    'rules' => [
        ReminderSystemRule::class,
        RankingSystemRule::class,
    ],

];
