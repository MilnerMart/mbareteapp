<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// una sola entrada: cada regla decide su horario desde Configuracion (system_rules) y rules:run corre las que vencieron
Schedule::command('rules:run')->everyMinute()->withoutOverlapping(15);
