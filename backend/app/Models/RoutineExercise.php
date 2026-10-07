<?php

namespace App\Models;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;
use stdClass;

class RoutineExercise {

    private const myTable = DbSchema::tableRoutineExercises;

    private int $routineId, $exerciseId;

    private ?int $sets, $reps;

    public static function allocNew(int $routineId, int $exerciseId, ?int $sets, ?int $reps):self{
        $self = new self();
        $self->routineId = $routineId;
        $self->exerciseId = $exerciseId;
        $self->sets = $sets;
        $self->reps = $reps;
        return $self;
    }

    /**
     * Si el ejercicio ya esta en la rutina actualiza series y repeticiones.
     */
    static function addOrUpdateRoutineExercise(DbConnector $dbConnect, int $routineId, int $exerciseId, ?int $sets, ?int $reps): void{
        $self = self::allocNew($routineId, $exerciseId, $sets, $reps);
        $self->writeToDb($dbConnect);
    }

    static function removeRoutineExercise(DbConnector $dbConnect, int $routineId, int $exerciseId): bool{
        return self::allocDbTable($dbConnect)->where('routine_id', $routineId)->where('exercise_id', $exerciseId)->delete() > 0;
    }

    /**
     * Ejercicios de la rutina con sus series y repeticiones, en el orden en que se agregaron.
     */
    static function queryExerciseListByRoutineId(DbConnector $dbConnect, int $routineId): array{
        return self::allocDbTable($dbConnect, 're')
        ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 're.exercise_id')
        ->where('re.routine_id', $routineId)
        ->where('e.status', '!=', EntityStatus::statusIdDeleted)
        ->orderBy('re.id')
        ->get(['e.id', 'e.name', 'e.muscle_id', 'e.recommended_rest_time', 're.sets', 're.reps'])
        ->map(fn(stdClass $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'muscle_id' => $row->muscle_id,
            'recommended_rest_time' => $row->recommended_rest_time,
            'sets' => $row->sets,
            'reps' => $row->reps,
        ])->all();
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private function routineExercise2Row(DbAdapter $dbAdapter): stdClass{
        $now = $dbAdapter->toDbDate(BaseHelper::utcNow());
        $row = new stdClass();
        $row->routine_id = $this->routineId;
        $row->exercise_id = $this->exerciseId;
        $row->sets = $this->sets;
        $row->reps = $this->reps;
        $row->created_at = $now;
        $row->updated_at = $now;
        return $row;
    }

    private function writeToDb(DbConnector $dbConnector): void{
        $writeArray = (array)$this->routineExercise2Row($dbConnector);
        self::allocDbTable($dbConnector)->upsert($writeArray, ['routine_id', 'exercise_id'], ['sets', 'reps', 'updated_at']);
    }

}
