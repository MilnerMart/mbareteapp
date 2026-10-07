<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder;
use stdClass;

class Exercise extends BaseEntity {
    use HasFactory;

    private const myTable = DbSchema::tableExercise;

    private string $name, $slug;

    private ?string $description;

    private int $muscleId, $restTime;

    public static function allocNew(string $name, string $slug, int $muscleId, int $restTime, ?string $description = null):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->muscleId = $muscleId;
        $self->description = $description;
        $self->restTime = $restTime;
        
        return $self;
    }

    function getModelId():int{
        return CoreModel::exerciseModelId;
    }

    function setName(string $name):void{
        $this->name = $name;
    }

    function getName():string{
        return $this->name; 
    }

    function setSlug(string $slug): void{
        $this->slug = $slug;
    }

    function getSlug(): string{
        return $this->slug;
    }

    function setMuscleId(int $muscleId): void{
        $this->muscleId = $muscleId;
    }

    function getMuscleId(): int{
        return $this->muscleId;
    }

    function setRestTime(int $restTime): void{
        $this->restTime = $restTime;
    }

    function getRestTime(): int{
        return $this->restTime;
    }

    function setDescription(?string $description): void{
        $this->description = $description;
    }

    function getDescription(): ?string{
        return $this->description;
    }

    public function muscle(){
        return $this->belongsTo(Muscle::class);
    }

    public function resources(){
        return $this->hasMany(Resource::class, 'exercise_id');
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' =>  $this->getName(),
            'slug' =>  $this->getSlug(),
            'muscle_id' => $this->muscleId,
            'description' => $this->description,
            'recommended_rest_time' => $this->restTime,
            'status' => $this->getStatusId()
        ];
    }

    static function queryByDbId(DbConnector $dbConnect, int $dbId):?self{
        $query = self::allocDbTable($dbConnect)->where('id', $dbId)->where('status', EntityStatus::statusIdActive);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Exercise']);
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->where('status', EntityStatus::statusIdActive)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Exercise']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):?self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::validationError('No se encuentra ejercicio con id: '.$dbId);
        }
        return $self;
    }

    static function queryListByMuscleId(DbConnector $dbConnect,  int $muscleId):array{
        $query = self::allocDbTable($dbConnect)->where('muscle_id', $muscleId)->where('status', EntityStatus::statusIdActive)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Exercise']);
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->exercise2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }
    
    static function row2Exercise(DbAdapter $dbAdapter, stdClass $row):self{
        $exercise = new Exercise();
        $exercise->initFromDbRow($dbAdapter, $row);
        $exercise->name = $row->name;
        $exercise->slug = $row->slug;
        $exercise->muscleId = $row->muscle_id;
        $exercise->description = $row->description;
        $exercise->restTime = $row->recommended_rest_time;

        return $exercise;
    }

     private function exercise2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->muscle_id = $this->muscleId;
        $row->recommended_rest_time = $this->restTime;
        $row->description = $this->description;
        return $row;
    }
}
