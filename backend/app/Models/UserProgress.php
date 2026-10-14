<?php

namespace App\Models;

use App\Db\DbConnector;
use App\Db\DbSchema;
use Carbon\CarbonImmutable;

class UserProgress {

    const pointsPerDay = 10, pointsPerStreakDay = 5, pointsPerMinute = 1, kgPerPoint = 100;

    const secondsPerRep = 3, defaultReps = 10, defaultRestSeconds = 60, fallbackMinutes = 2;

    const rankPointMap = [
        'novice' => 0,
        'beginner' => 150,
        'steady' => 400,
        'intermediate' => 900,
        'advanced' => 1800,
        'elite' => 3500,
        'beast' => 6000,
    ];

    static function isHigherRank(string $rank, string $thanRank): bool{
        return (self::rankPointMap[$rank] ?? 0) > (self::rankPointMap[$thanRank] ?? 0);
    }

    static function build(DbConnector $dbConnect, int $userId): array{
        $rowList = RoutineExerciseLog::allocDbTable($dbConnect, 'l')
            ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 'l.exercise_id')
            ->where('l.user_id', $userId)
            ->get(['l.done_on', 'l.sets', 'l.reps', 'l.duration_minutes', 'l.weight_kg', 'e.recommended_rest_time']);

        $minutes = 0;
        $kilos = 0.0;
        $daySet = [];
        foreach ($rowList as $row) {
            $daySet[substr((string)$row->done_on, 0, 10)] = true;
            $minutes += self::estimateMinutes($row);
            if($row->weight_kg !== null && $row->sets && $row->reps){
                $kilos += (float)$row->weight_kg * (int)$row->sets * (int)$row->reps;
            }
        }
        $dayList = array_keys($daySet);
        sort($dayList);
        [$streakDays, $bestStreak, $currentStreak] = self::countStreaks($dayList);

        $points = count($dayList) * self::pointsPerDay
            + $streakDays * self::pointsPerStreakDay
            + $minutes * self::pointsPerMinute
            + intdiv((int)$kilos, self::kgPerPoint);

        return [
            'points' => $points,
            'trainingDays' => count($dayList),
            'currentStreak' => $currentStreak,
            'bestStreak' => $bestStreak,
            'minutes' => $minutes,
            'kilos' => (int)round($kilos),
        ] + self::buildRankModel($points);
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

    private static function buildRankModel(int $points): array{
        $rank = array_key_first(self::rankPointMap);
        $nextRank = null;
        foreach (self::rankPointMap as $slug => $minPoints) {
            if($points >= $minPoints){
                $rank = $slug;
            } elseif($nextRank === null){
                $nextRank = $slug;
            }
        }
        $rankPoints = self::rankPointMap[$rank];
        $nextPoints = $nextRank ? self::rankPointMap[$nextRank] : null;
        return [
            'rank' => $rank,
            'nextRank' => $nextRank,
            'nextRankPoints' => $nextPoints,
            'progressPercent' => $nextPoints ? (int)floor(100 * ($points - $rankPoints) / ($nextPoints - $rankPoints)) : 100,
        ];
    }
}
