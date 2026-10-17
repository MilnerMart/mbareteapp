<?php

namespace App\Models;

use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
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
        $restDayList = User::queryRestDayMapByIds($dbConnect, [$userId])[$userId] ?? [];
        [, $bestStreak, $currentStreak] = self::countStreaks($dayList, $restDayList);

        // lo que vale cada cosa, el tope por dia y lo que pide cada rango los configura el admin (ProgressSettings).
        // El tope hace que una sola sesion larga no alcance para subir de rango: hace falta volver otros dias.
        $settings = ProgressSettings::load($dbConnect);
        $points = array_sum(self::buildDayPointsMap($settings, $dayMap, $restDayList));

        return [
            'points' => $points,
            'trainingDays' => count($dayList),
            'currentStreak' => $currentStreak,
            'bestStreak' => $bestStreak,
            'minutes' => array_sum(array_column($dayMap, 'minutes')),
            'kilos' => (int)round(array_sum(array_column($dayMap, 'kilos'))),
        ] + $settings->buildRankModel($points);
    }

    // $dayMap ordenado por dia: [dia => ['minutes' =>, 'kilos' =>]] => [dia => puntos de ese dia]
    static function buildDayPointsMap(ProgressSettings $settings, array $dayMap, array $restDayList = []): array{
        $pointsMap = [];
        $previousDay = null;
        foreach ($dayMap as $day => $dayTotals) {
            $isStreakDay = $previousDay && self::keepsStreak(BaseHelper::parseDate($previousDay), BaseHelper::parseDate($day), $restDayList);
            $dayPoints = $settings->scoring('pointsPerDay')
                + ($isStreakDay ? $settings->scoring('pointsPerStreakDay') : 0)
                + $dayTotals['minutes'] * $settings->scoring('pointsPerMinute')
                + intdiv((int)$dayTotals['kilos'], $settings->scoring('kgPerPoint'));
            $pointsMap[$day] = min($dayPoints, $settings->scoring('maxPointsPerDay'));
            $previousDay = $day;
        }
        return $pointsMap;
    }

    static function estimateMinutes(object $row): int{
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

    // la racha sigue si entre dos dias entrenados solo hubo dias de descanso; entrenar en un dia de descanso tambien suma
    static function countStreaks(array $sortedDayList, array $restDayList = []): array{
        $streakDays = 0;
        $bestStreak = 0;
        $streak = 0;
        $previous = null;
        foreach ($sortedDayList as $day) {
            $date = BaseHelper::parseDate($day);
            $isConsecutive = $previous && self::keepsStreak($previous, $date, $restDayList);
            $streak = $isConsecutive ? $streak + 1 : 1;
            $streakDays += $isConsecutive ? 1 : 0;
            $bestStreak = max($bestStreak, $streak);
            $previous = $date;
        }

        $today = BaseHelper::parseDate(RoutineExerciseLog::today());
        $isAlive = $previous && self::keepsStreak($previous, $today, $restDayList);
        return [$streakDays, $bestStreak, $isAlive ? $streak : 0];
    }

    static function keepsStreak(CarbonImmutable $lastDay, CarbonImmutable $day, array $restDayList): bool{
        for ($gapDay = $lastDay->addDay(); $gapDay->lt($day); $gapDay = $gapDay->addDay()) {
            if(!in_array($gapDay->dayOfWeekIso, $restDayList, true)){
                return false;
            }
        }
        return true;
    }
}
