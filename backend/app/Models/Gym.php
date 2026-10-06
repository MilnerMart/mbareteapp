<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use App\Models\BaseEntity;
use App\PublicException;
use Illuminate\Database\Query\Builder;
use stdClass;

class Gym extends BaseEntity {

    private const myTable = DbSchema::tableGymEntity;

    const leoncioGymSlug = 'leoncion-gym';

    private string $name, $slug;

    private ?int $ownerId, $alumnsCount;
    
    public static function allocNew(string $name, string $slug, int $ownerId, ?int $alumnsCount):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->ownerId = $ownerId;
        $self->alumnsCount = $alumnsCount;

        return $self;
    }

    function getModelId(): int{
        return CoreModel::gymEntityModelId;
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

    function getOwnerId():int{
        return $this->ownerId;
    }
    private function gym2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->owner_id = $this->ownerId;
        $row->alumns_count = $this->alumnsCount;
        return $row;
    }

    static function row2Gym(DbAdapter $dbAdapter, stdClass $row):self{
        $gym = new Gym();
        $gym->initFromDbRow($dbAdapter, $row);
        $gym->name = $row->name;
        $gym->slug = $row->slug;
        $gym->ownerId = $row->owner_id;
        $gym->alumnsCount = $row->alumns_count;
        return $gym;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' =>  $this->getName(),
            'slug' =>  $this->getSlug(),
            'ownerId' => $this->ownerId,
            'alumnsCount' => $this->alumnsCount,
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Gym']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocDbTable($dbConnect)->where('id', $dbId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchSingle($query, [self::class ,'row2Gym']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):?self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::validationError('No se encuentra musculo con id: '.$dbId);
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
        $writeArray = (array)$this->gym2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }

}