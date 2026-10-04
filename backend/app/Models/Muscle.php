<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\PublicException;
use Dom\Entity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder;
use stdClass;

class Muscle extends BaseEntity {

    use HasFactory;

    private const myTable = DbSchema::tableMuscles;

    private string $name, $slug, $description;
    private int $recommended_rest_days;

    public static function allocMuscle(string $name, string $slug, string $description,int $restDays): self{
        $self = new Muscle();
        $self->name = $name;
        $self->slug = $slug;
        $self->description = $description;
        $self->recommended_rest_days = $restDays;
        return $self;
    }

    function getModelId(): int{
        return CoreModel::resourceModelId;
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

    function setRestDays(int $restDays): void{
        $this->recommended_rest_days = $restDays;
    }

    function getRestDays(): int{
        return $this->recommended_rest_days;
    }

    function setImg(string $image): void{
        $this->image_url = $image;
    }
    
    function getImg(): string{
        return $this->image_url;
    }

    function setDescription(string $description): void{
        $this->description = $description;
    }

    function getDescription(): string{
        return $this->description;
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' =>  $this->getName(),
            'slug' =>  $this->getSlug(),
            'recommended_rest_days' => $this->getRestDays(),
            'description' => $this->getDescription(),
        ];
    }
    static function row2Muscle(DbAdapter $dbAdapter, stdClass $row):self{
        $muscle = new Muscle();
        $muscle->initFromDbRow($dbAdapter, $row);
        $muscle->name = $row->name;
        $muscle->slug = $row->slug;
        $muscle->recommended_rest_days = $row->recommended_rest_days;
        $muscle->description = $row->description;
        return $muscle;
    }

    private function muscle2row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->recommended_rest_days = $this->recommended_rest_days;
        $row->description = $this->description;
        return $row;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocDbTable($dbConnect)->where('id', $dbId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchSingle($query, [self::class ,'row2Muscle']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):?self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::validationError('No se encuentra musculo con id: '.$dbId);
        }
        return $self;
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Muscle']);
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->muscle2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }
}
