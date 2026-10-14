<?php

namespace App\Models;

use App\Core\EntityStatus;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;

class RoutineExerciseLog {

    private const myTable = DbSchema::tableRoutineExerciseLogs;

    const dayTimezone = 'America/Asuncion';

    static function today(): string{
        return now(self::dayTimezone)->toDateString();
    }

    static function syncDoneToday(DbConnector $dbConnect, int $userId, int $routineId, array $weightKgMap): void{
        $today = self::today();
        $exerciseIdList = array_map('intval', array_keys($weightKgMap));
        self::allocDbTable($dbConnect)->where('user_id', $userId)->where('routine_id', $routineId)->where('done_on', $today)
            ->whereNotIn('exercise_id', $exerciseIdList)->delete();
        if(!$exerciseIdList){
            return;
        }

        $volumeMap = RoutineExercise::allocDbTable($dbConnect)->where('routine_id', $routineId)->whereIn('exercise_id', $exerciseIdList)
            ->get(['exercise_id', 'sets', 'reps', 'duration_minutes'])->keyBy(fn($row) => (int)$row->exercise_id);
        $now = $dbConnect->toDbDate(BaseHelper::utcNow());
        $rowList = [];
        foreach ($weightKgMap as $exerciseId => $weightKg) {
            $volume = $volumeMap[(int)$exerciseId] ?? null;
            $rowList[] = [
                'user_id' => $userId,
                'routine_id' => $routineId,
                'exercise_id' => (int)$exerciseId,
                'done_on' => $today,
                'sets' => $volume?->sets,
                'reps' => $volume?->reps,
                'duration_minutes' => $volume?->duration_minutes,
                'weight_kg' => $weightKg,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        self::allocDbTable($dbConnect)->upsert($rowList, ['user_id', 'routine_id', 'exercise_id', 'done_on'],
            ['sets', 'reps', 'duration_minutes', 'weight_kg', 'updated_at']);
    }

    // por dia y rutina: cuantos ejercicios marco y cuantos tiene hoy la rutina (completa si hizo todos)
    static function queryTrainingDayList(DbConnector $dbConnect, int $userId, string $fromDay, string $toDay): array{
        $totalQuery = RoutineExercise::allocDbTable($dbConnect, 're')
        ->selectRaw('count(*)')->whereColumn('re.routine_id', 'l.routine_id');
        $rowList = self::allocDbTable($dbConnect, 'l')
        ->join(DbSchema::tableRoutines.' as r', 'r.id', '=', 'l.routine_id')
        ->where('l.user_id', $userId)
        ->whereBetween('l.done_on', [$fromDay, $toDay])
        ->groupBy('l.done_on', 'l.routine_id', 'r.name')
        ->orderBy('l.done_on')->orderBy('r.name')
        ->select('l.done_on', 'l.routine_id', 'r.name')
        ->selectRaw('count(distinct l.exercise_id) as done_count')
        ->selectSub($totalQuery, 'total_count')
        ->get();

        return $rowList->map(fn($row) => [
            'day' => substr((string)$row->done_on, 0, 10),
            'routineId' => (int)$row->routine_id,
            'routineName' => $row->name,
            'doneCount' => (int)$row->done_count,
            'totalCount' => (int)$row->total_count,
            'isCompleted' => (int)$row->total_count > 0 && (int)$row->done_count >= (int)$row->total_count,
        ])->all();
    }

    static function queryDoneTodayWeightMap(DbConnector $dbConnect, int $userId, int $routineId): array{
        $weightMap = [];
        foreach (self::allocDbTable($dbConnect)->where('user_id', $userId)->where('routine_id', $routineId)
                     ->where('done_on', self::today())->get(['exercise_id', 'weight_kg']) as $row) {
            $weightMap[(int)$row->exercise_id] = $row->weight_kg !== null ? (float)$row->weight_kg : null;
        }
        return $weightMap;
    }

    static function queryFavoriteRoutineId(DbConnector $dbConnect, int $userId): ?int{
        $routineId = self::allocDbTable($dbConnect, 'l')
            ->join(DbSchema::tableRoutines.' as r', 'r.id', '=', 'l.routine_id')
            ->where('l.user_id', $userId)
            ->where('r.status', '!=', EntityStatus::statusIdDeleted)
            ->groupBy('l.routine_id')
            ->orderByRaw('count(distinct l.done_on) desc')
            ->orderByRaw('max(l.done_on) desc')
            ->value('l.routine_id');
        return $routineId ? (int)$routineId : null;
    }

    static function queryFavoriteExerciseId(DbConnector $dbConnect, int $userId): ?int{
        $exerciseId = self::allocDbTable($dbConnect, 'l')
            ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 'l.exercise_id')
            ->where('l.user_id', $userId)
            ->where('e.status', EntityStatus::statusIdActive)
            ->groupBy('l.exercise_id')
            ->orderByRaw('count(*) desc')
            ->orderByRaw('max(l.done_on) desc')
            ->value('l.exercise_id');
        return $exerciseId ? (int)$exerciseId : null;
    }

    static function queryFavoriteMuscleId(DbConnector $dbConnect, int $userId): ?int{
        $muscleId = self::allocDbTable($dbConnect, 'l')
            ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 'l.exercise_id')
            ->join(DbSchema::tableMuscles.' as m', 'm.id', '=', 'e.muscle_id')
            ->where('l.user_id', $userId)
            ->where('m.status', EntityStatus::statusIdActive)
            ->groupBy('e.muscle_id')
            ->orderByRaw('count(*) desc')
            ->orderByRaw('max(l.done_on) desc')
            ->value('e.muscle_id');
        return $muscleId ? (int)$muscleId : null;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }
}
