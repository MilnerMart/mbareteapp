<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\HasDbData;
use App\Helpers\BaseHelper;
use App\PublicException;
use Illuminate\Database\Query\Builder;
use stdClass;

class Routine extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableRoutines;

    private string $name, $slug;

    // frequency: dias por semana, restTime: segundos de descanso entre ejercicios (la API lo expone en minutos)
    private int $frequency, $restTime, $ownerId;

    public static function allocNew(string $name, string $slug, int $ownerId, int $frequency, int $restTime):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->ownerId = $ownerId;
        $self->frequency = $frequency;
        $self->restTime = $restTime;

        return $self;
    }

    function getModelId():int{
        return CoreModel::routineModelId;
    }

    function setName(string $name):void{
        $this->name = $name;
    }

    function getName():string{
        return $this->name;
    }

    function getSlug(): string{
        return $this->slug;
    }

    function setFrequency(int $frequency):void{
        $this->frequency = $frequency;
    }

    function getFrequency():int{
        return $this->frequency;
    }

    function setRestTime(int $restTime):void{
        $this->restTime = $restTime;
    }

    function getRestTime():int{
        return $this->restTime;
    }

    static function minutesToSeconds(float $minutes):int{
        return (int)round($minutes * 60);
    }

    function getRestMinutes():float{
        return round($this->restTime / 60, 1);
    }

    function getOwnerId():int{
        return $this->ownerId;
    }

    function isOwnedBy(int $userId):bool{
        return $this->ownerId === $userId;
    }

    function setDescription(?string $description):void{
        $this->_setDataItem('description', $description ?: null);
    }

    function getDescription():?string{
        return $this->_getDataItem('description');
    }

    private function routine2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->owner_id = $this->ownerId;
        $row->frequency = $this->frequency;
        $row->rest_between_exercises = $this->restTime;
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2Routine(DbAdapter $dbAdapter, stdClass $row):self{
        $routine = new Routine();
        $routine->initFromDbRow($dbAdapter, $row);
        $routine->name = $row->name;
        $routine->slug = $row->slug;
        $routine->ownerId = (int)$row->owner_id;
        $routine->frequency = $row->frequency;
        $routine->restTime = $row->rest_between_exercises;
        $routine->_loadDataFromDb($row->data);
        return $routine;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->getDescription(),
            'frequency' => $this->frequency,
            'restMinutes' => $this->getRestMinutes(),
            'ownerId' => $this->ownerId,
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect): Builder{
        return self::allocDbTable($dbConnect, 'r')
        ->where('r.status', '!=', EntityStatus::statusIdDeleted)
        ->select('r.*')->orderBy('r.name');
    }

    static function queryList(DbConnector $dbConnect):array{
        return $dbConnect->fetchAll(self::allocSelectQuery($dbConnect), [self::class, 'row2Routine']);
    }

    static function queryListByOwnerId(DbConnector $dbConnect, int $ownerId):array{
        $query = self::allocSelectQuery($dbConnect)->where('r.owner_id', $ownerId);
        return $dbConnect->fetchAll($query, [self::class, 'row2Routine']);
    }

    static function queryListByAssignedUserId(DbConnector $dbConnect, int $userId):array{
        $query = self::allocSelectQuery($dbConnect)
        ->join(DbSchema::tableUserRoutines.' as ur', 'ur.routine_id', '=', 'r.id')
        ->where('ur.user_id', $userId);
        return $dbConnect->fetchAll($query, [self::class, 'row2Routine']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocSelectQuery($dbConnect)->where('r.id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Routine']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::notFoundError('No se encuentra rutina con id: '.$dbId);
        }
        return $self;
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->routine2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }

}
