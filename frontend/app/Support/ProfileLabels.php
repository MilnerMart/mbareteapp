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

    static function rankLabel(?string $rank, ?string $gender = null): string
    {
        $rank = isset(self::rankLabelMap[$rank]) ? $rank : 'novice';
        return $gender === 'female' ? (self::femaleRankLabelMap[$rank] ?? self::rankLabelMap[$rank]) : self::rankLabelMap[$rank];
    }

    static function formatMinutes(int $minutes): string
    {
        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }
}
