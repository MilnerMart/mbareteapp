<?php

namespace App\Models;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use Illuminate\Database\Query\Builder;
use stdClass;

class RoutineExercise {

    private const myTable = DbSchema::tableRoutineExercises;

    private int $routineId, $exerciseId;

    private ?int $sets, $reps, $durationMinutes;

    private ?string $notes;

    public static function allocNew(int $routineId, int $exerciseId, ?int $sets, ?int $reps, ?int $durationMinutes = null, ?string $notes = null):self{
        $self = new self();
        $self->routineId = $routineId;
        $self->exerciseId = $exerciseId;
        $self->sets = $sets;
        $self->reps = $reps;
        $self->durationMinutes = $durationMinutes;
        $self->notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        return $self;
    }

    /**
     * Si el ejercicio ya esta en la rutina actualiza series, repeticiones, minutos y notas.
     */
    static function addOrUpdateRoutineExercise(DbConnector $dbConnect, int $routineId, int $exerciseId, ?int $sets, ?int $reps,
        ?int $durationMinutes = null, ?string $notes = null): void{
        $self = self::allocNew($routineId, $exerciseId, $sets, $reps, $durationMinutes, $notes);
        $self->writeToDb($dbConnect);
    }

    static function syncRoutineExercises(DbConnector $dbConnect, int $routineId, array $exerciseList): void{
        $currentIdList = self::allocDbTable($dbConnect)->where('routine_id', $routineId)->pluck('exercise_id')->map(fn($id) => (int)$id)->all();
        $exerciseIdList = array_map(fn(array $exercise) => (int)$exercise['exercise_id'], $exerciseList);
        if(array_diff($exerciseIdList, $currentIdList)){
            throw PublicException::validationError('Solo puedes editar u ordenar ejercicios que ya estan en la rutina');
        }

        self::allocDbTable($dbConnect)->where('routine_id', $routineId)->whereNotIn('exercise_id', $exerciseIdList)->delete();
        foreach (array_values($exerciseList) as $index => $exercise) {
            $self = self::allocNew($routineId, (int)$exercise['exercise_id'], $exercise['sets'] ?? null, $exercise['reps'] ?? null,
                $exercise['duration_minutes'] ?? null, $exercise['notes'] ?? null);
            $row = $self->routineExercise2Row($dbConnect);
            self::allocDbTable($dbConnect)->where('routine_id', $routineId)->where('exercise_id', $self->exerciseId)->update([
                'sets' => $row->sets,
                'reps' => $row->reps,
                'duration_minutes' => $row->duration_minutes,
                'notes' => $row->notes,
                'position' => $index + 1,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    static function removeRoutineExercise(DbConnector $dbConnect, int $routineId, int $exerciseId): bool{
        return self::allocDbTable($dbConnect)->where('routine_id', $routineId)->where('exercise_id', $exerciseId)->delete() > 0;
    }

    /**
     * Ejercicios de la rutina con sus series y repeticiones, en el orden que eligio quien la arma.
     */
    static function queryExerciseListByRoutineId(DbConnector $dbConnect, int $routineId): array{
        return self::allocDbTable($dbConnect, 're')
        ->join(DbSchema::tableExercise.' as e', 'e.id', '=', 're.exercise_id')
        ->leftJoin(DbSchema::tableMuscles.' as m', 'm.id', '=', 'e.muscle_id')
        ->where('re.routine_id', $routineId)
        ->where('e.status', '!=', EntityStatus::statusIdDeleted)
        ->orderBy('re.position')
        ->orderBy('re.id')
        ->get(['e.id', 'e.name', 'e.description', 'e.muscle_id', 'e.recommended_rest_time', 're.sets', 're.reps', 're.duration_minutes', 're.notes', 'm.kind as muscle_kind'])
        ->map(fn(stdClass $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'muscle_id' => $row->muscle_id,
            'muscleKind' => Muscle::kindSlugFromId($row->muscle_kind !== null ? (int)$row->muscle_kind : null),
            'recommended_rest_time' => $row->recommended_rest_time,
            'sets' => $row->sets,
            'reps' => $row->reps,
            'duration_minutes' => $row->duration_minutes,
            'notes' => $row->notes,
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
        $row->duration_minutes = $this->durationMinutes;
        $row->notes = $this->notes;
        $row->created_at = $now;
        $row->updated_at = $now;
        return $row;
    }

    // los nuevos van al final; al actualizar uno existente se mantiene su lugar
    private function writeToDb(DbConnector $dbConnector): void{
        $writeArray = (array)$this->routineExercise2Row($dbConnector);
        $writeArray['position'] = (int)self::allocDbTable($dbConnector)->where('routine_id', $this->routineId)->max('position') + 1;
        self::allocDbTable($dbConnector)->upsert($writeArray, ['routine_id', 'exercise_id'], ['sets', 'reps', 'duration_minutes', 'notes', 'updated_at']);
    }

}
