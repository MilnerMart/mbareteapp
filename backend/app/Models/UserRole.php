<?php

namespace App\Models;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Illuminate\Database\Query\Builder;
use stdClass;

class UserRole {

    private const myTable = DbSchema::tableUserRoles;

    private int $userId, $roleId;

    public static function allocNew(int $userId, int $roleId):self{
        $self = new self();
        $self->userId = $userId;
        $self->roleId = $roleId;
        return $self;
    }

    static function addNewUserRole(DbConnector $dbConnect, int $userId, int $roleId){
        $self = self::allocNew($userId, $roleId);
        $self->writeToDb($dbConnect);
    }

    static function hasUserRole(DbConnector $dbConnect, int $userId, int $roleId): bool{
        return self::allocDbTable($dbConnect)->where('user_id', $userId)->where('role_id', $roleId)->exists();
    }

    static private function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private function userRole2Row(DbAdapter $dbAdapter): stdClass{
        $row = new stdClass();
        $row->user_id = $this->userId;
        $row->role_id = $this->roleId;
        return $row;
    }
    
    private function writeToDb(DbConnector $dbConnector): void{
        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->userRole2Row ($dbConnector);
        $writable->insertGetId($writeArray);
    }
    
}