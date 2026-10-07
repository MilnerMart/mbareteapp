<?php

namespace App\Models;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;
use stdClass;

class GymUser {

    private const myTable = DbSchema::tableGymUsers;

    private int $gymId, $userId;

    public static function allocNew(int $gymId, int $userId):self{
        $self = new self();
        $self->gymId = $gymId;
        $self->userId = $userId;
        return $self;
    }

    static function addNewGymUser(DbConnector $dbConnect, int $gymId, int $userId){
        $self = self::allocNew($gymId, $userId);
        $self->writeToDb($dbConnect);
    }

    static function queryUserIdListByGymId(DbConnector $dbConnect, int $gymId): array{
        return self::allocDbTable($dbConnect)->where('gym_id', $gymId)
        ->orderBy('created_at')->pluck('user_id')->all();
    }

    static function removeGymUser(DbConnector $dbConnect, int $gymId, int $userId): bool{
        return self::allocDbTable($dbConnect)->where('gym_id', $gymId)->where('user_id', $userId)->delete() > 0;
    }

    static function isUserInGymOwnedBy(DbConnector $dbConnect, int $userId, int $ownerId): bool{
        return self::allocDbTable($dbConnect, 'gu')
        ->join(DbSchema::tableGymEntity.' as g', 'g.id', '=', 'gu.gym_id')
        ->where('gu.user_id', $userId)
        ->where('g.owner_id', $ownerId)
        ->where('g.status', '!=', EntityStatus::statusIdDeleted)
        ->exists();
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private function gymUser2Row(DbAdapter $dbAdapter): stdClass{
        $now = $dbAdapter->toDbDate(BaseHelper::utcNow());
        $row = new stdClass();
        $row->gym_id = $this->gymId;
        $row->user_id = $this->userId;
        $row->created_at = $now;
        $row->updated_at = $now;
        return $row;
    }

    private function writeToDb(DbConnector $dbConnector): void{
        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->gymUser2Row($dbConnector);
        $writable->insertOrIgnore($writeArray);
    }

}
