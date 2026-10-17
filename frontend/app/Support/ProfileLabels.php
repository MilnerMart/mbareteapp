<?php

namespace App\Support;

class ProfileLabels
{
    const goalLabelMap = [
        'hypertrophy' => 'Ganar masa muscular',
        'fat-loss' => 'Bajar de peso',
        'strength' => 'Ganar fuerza',
        'endurance' => 'Mejorar resistencia',
        'toning' => 'Tonificar y definir',
        'health' => 'Salud y bienestar',
    ];

    const genderLabelMap = [
        'male' => 'Masculino',
        'female' => 'Femenino',
        'unspecified' => 'Prefiero no especificar',
    ];

    // dia ISO de la semana, igual que el backend (User::weekDayList)
    const weekDayLabelMap = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    const maxRestDays = 6;

    // nombres de los datos de perfil que el backend pide completar (User::requiredProfileFieldList)
    const profileFieldLabelMap = [
        'gender' => 'genero',
        'weight' => 'peso',
        'height' => 'altura',
        'age' => 'edad',
        'goal' => 'objetivo',
        'name' => 'nombre',
        'last_name' => 'apellido',
    ];

    const rankLabelMap = [
        'novice' => 'Novato',
        'beginner' => 'Principiante',
        'steady' => 'Constante',
        'intermediate' => 'Intermedio',
        'advanced' => 'Avanzado',
        'elite' => 'Élite',
        'beast' => 'Bestia',
    ];

    const femaleRankLabelMap = [
        'novice' => 'Novata',
        'intermediate' => 'Intermedia',
        'advanced' => 'Avanzada',
    ];

    static function goalLabel(?string $goal): ?string
    {
        return self::goalLabelMap[$goal] ?? null;
    }

    static function genderLabel(?string $gender): ?string
    {
        return self::genderLabelMap[$gender] ?? null;
    }

    static function rankLabel(?string $rank, ?string $gender = null): string
    {
        $rank = isset(self::rankLabelMap[$rank]) ? $rank : 'novice';
        return $gender === 'female' ? (self::femaleRankLabelMap[$rank] ?? self::rankLabelMap[$rank]) : self::rankLabelMap[$rank];
    }

    static function restDaysText(array $restDayList): string
    {
        $labelList = array_values(array_map('mb_strtolower', array_intersect_key(self::weekDayLabelMap, array_flip(array_map('intval', $restDayList)))));
        if(!$labelList){
            return 'Sin dias de descanso: para mantener la racha hay que entrenar todos los dias.';
        }
        $last = array_pop($labelList);
        return 'Dias de descanso (no cortan la racha): '.($labelList ? implode(', ', $labelList).' y '.$last : $last).'.';
    }

    static function formatMinutes(int $minutes): string
    {
        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }
}
