<?php

namespace App\Models;

use App\Db\DbConnector;
use App\Db\DbSchema;
use Carbon\CarbonImmutable;

class UserProgress {

    const secondsPerRep = 3, defaultReps = 10, defaultRestSeconds = 60, fallbackMinutes = 2;

    static function isHigherRank(string $rank, string $thanRank): bool{
        return ProgressSettings::isHigherRank($rank, $thanRank);
    }

    static function build(DbConnector $dbConnect, int $userId): array{
        $rowList = RoutineExerciseLog::allocDbTable($dbConnect, 'l')
            ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 'l.exercise_id')
            ->where('l.user_id', $userId)
            ->get(['l.done_on', 'l.sets', 'l.reps', 'l.duration_minutes', 'l.weight_kg', 'e.recommended_rest_time']);

        $dayMap = [];
        foreach ($rowList as $row) {
            $day = substr((string)$row->done_on, 0, 10);
            $dayMap[$day] ??= ['minutes' => 0, 'kilos' => 0.0];
            $dayMap[$day]['minutes'] += self::estimateMinutes($row);
            if($row->weight_kg !== null && $row->sets && $row->reps){
                $dayMap[$day]['kilos'] += (float)$row->weight_kg * (int)$row->sets * (int)$row->reps;
            }
        }
        ksort($dayMap);
        $dayList = array_keys($dayMap);
        [, $bestStreak, $currentStreak] = self::countStreaks($dayList);

        // lo que vale cada cosa, el tope por dia y lo que pide cada rango los configura el admin (ProgressSettings).
        // El tope hace que una sola sesion larga no alcance para subir de rango: hace falta volver otros dias.
        $settings = ProgressSettings::load($dbConnect);
        $points = 0;
        $previousDay = null;
        foreach ($dayMap as $day => $dayTotals) {
            $isStreakDay = $previousDay && CarbonImmutable::parse($previousDay)->addDay()->isSameDay(CarbonImmutable::parse($day));
            $dayPoints = $settings->scoring('pointsPerDay')
                + ($isStreakDay ? $settings->scoring('pointsPerStreakDay') : 0)
                + $dayTotals['minutes'] * $settings->scoring('pointsPerMinute')
                + intdiv((int)$dayTotals['kilos'], $settings->scoring('kgPerPoint'));
            $points += min($dayPoints, $settings->scoring('maxPointsPerDay'));
            $previousDay = $day;
        }

        return [
            'points' => $points,
            'trainingDays' => count($dayList),
            'currentStreak' => $currentStreak,
            'bestStreak' => $bestStreak,
            'minutes' => array_sum(array_column($dayMap, 'minutes')),
            'kilos' => (int)round(array_sum(array_column($dayMap, 'kilos'))),
        ] + $settings->buildRankModel($points);
    }

    private static function estimateMinutes(object $row): int{
        if($row->duration_minutes){
            return (int)$row->duration_minutes;
        }
        if(!$row->sets){
            return self::fallbackMinutes;
        }
        $sets = (int)$row->sets;
        $restSeconds = $row->recommended_rest_time ? (int)$row->recommended_rest_time : self::defaultRestSeconds;
        $seconds = $sets * ($row->reps ? (int)$row->reps : self::defaultReps) * self::secondsPerRep + max($sets - 1, 0) * $restSeconds;
        return max(1, (int)ceil($seconds / 60));
    }

    private static function countStreaks(array $sortedDayList): array{
        $streakDays = 0;
        $bestStreak = 0;
        $streak = 0;
        $previous = null;
        foreach ($sortedDayList as $day) {
            $date = CarbonImmutable::parse($day);
            $isConsecutive = $previous && $previous->addDay()->isSameDay($date);
            $streak = $isConsecutive ? $streak + 1 : 1;
            $streakDays += $isConsecutive ? 1 : 0;
            $bestStreak = max($bestStreak, $streak);
            $previous = $date;
        }

        $today = CarbonImmutable::parse(RoutineExerciseLog::today());
        $isAlive = $previous && ($previous->isSameDay($today) || $previous->addDay()->isSameDay($today));
        return [$streakDays, $bestStreak, $isAlive ? $streak : 0];
    }
}
