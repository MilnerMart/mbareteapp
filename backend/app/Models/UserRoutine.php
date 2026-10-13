<?php

namespace App\Models;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;
use stdClass;

class UserRoutine {

    private const myTable = DbSchema::tableUserRoutines;

    private int $userId, $routineId;

    private ?int $assignedBy;

    public static function allocNew(int $userId, int $routineId, ?int $assignedBy):self{
        $self = new self();
        $self->userId = $userId;
        $self->routineId = $routineId;
        $self->assignedBy = $assignedBy;
        return $self;
    }

    static function addNewUserRoutine(DbConnector $dbConnect, int $userId, int $routineId, ?int $assignedBy): void{
        $self = self::allocNew($userId, $routineId, $assignedBy);
        $self->writeToDb($dbConnect);
    }

    static function removeUserRoutine(DbConnector $dbConnect, int $userId, int $routineId): bool{
        return self::allocDbTable($dbConnect)->where('user_id', $userId)->where('routine_id', $routineId)->delete() > 0;
    }

    static function queryUserIdListByRoutineId(DbConnector $dbConnect, int $routineId): array{
        return self::allocDbTable($dbConnect)->where('routine_id', $routineId)
        ->orderBy('created_at')->pluck('user_id')->all();
    }

    static function isAssigned(DbConnector $dbConnect, int $userId, int $routineId): bool{
        return self::allocDbTable($dbConnect)->where('user_id', $userId)->where('routine_id', $routineId)->exists();
    }

    /**
     * Rutinas asignadas agrupadas por usuario: [userId => [['id'=>, 'name'=>], ...]]
     * Con $viewerId solo las que ese usuario creo o asigno: un entrenador no ve lo que asignaron
     * otros entrenadores (el alumno puede estar en varios gimnasios) ni el admin.
     */
    static function queryRoutineMapByUserIds(DbConnector $dbConnect, array $userIdList, ?int $viewerId = null): array{
        if(!$userIdList){
            return [];
        }
        $query = self::allocDbTable($dbConnect, 'ur')
        ->join(DbSchema::tableRoutines.' as r', 'r.id', '=', 'ur.routine_id')
        ->whereIn('ur.user_id', $userIdList)
        ->where('r.status', '!=', EntityStatus::statusIdDeleted)
        ->orderBy('r.name');
        if($viewerId !== null){
            $query->where(fn(Builder $where) => $where->where('r.owner_id', $viewerId)->orWhere('ur.assigned_by', $viewerId));
        }
        $rowList = $query->get(['ur.user_id', 'r.id', 'r.name']);

        $routineMap = [];
        foreach ($rowList as $row) {
            $routineMap[$row->user_id][] = ['id' => $row->id, 'name' => $row->name];
        }
        return $routineMap;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private function userRoutine2Row(DbAdapter $dbAdapter): stdClass{
        $now = $dbAdapter->toDbDate(BaseHelper::utcNow());
        $row = new stdClass();
        $row->user_id = $this->userId;
        $row->routine_id = $this->routineId;
        $row->assigned_by = $this->assignedBy;
        $row->created_at = $now;
        $row->updated_at = $now;
        return $row;
    }

    private function writeToDb(DbConnector $dbConnector): void{
        $writeArray = (array)$this->userRoutine2Row($dbConnector);
        self::allocDbTable($dbConnector)->insertOrIgnore($writeArray);
    }

}
