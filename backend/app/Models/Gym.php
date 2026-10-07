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

    const baseGymSlug = self::leoncioGymSlug;

    private string $name, $slug;

    private ?int $ownerId;

    private int $alumnsCount = 0;
    
    public static function allocNew(string $name, string $slug, int $ownerId):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->ownerId = $ownerId;

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

    function setOwnerId(int $ownerId): void{
        $this->ownerId = $ownerId;
    }

    function getOwnerId():int{
        return $this->ownerId;
    }

    function getAlumnsCount():int{
        return $this->alumnsCount;
    }

    function isOwnedBy(int $userId):bool{
        return $this->ownerId === $userId;
    }
    private function gym2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->owner_id = $this->ownerId;
        return $row;
    }

    static function row2Gym(DbAdapter $dbAdapter, stdClass $row):self{
        $gym = new Gym();
        $gym->initFromDbRow($dbAdapter, $row);
        $gym->name = $row->name;
        $gym->slug = $row->slug;
        $gym->ownerId = $row->owner_id;
        $gym->alumnsCount = (int)($row->alumns_count ?? 0);
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

    private static function allocSelectQuery(DbConnector $dbConnect): Builder{
        $alumnsCountQuery = GymUser::allocDbTable($dbConnect, 'gu')
        ->selectRaw('count(*)')->whereColumn('gu.gym_id', 'g.id');
        return self::allocDbTable($dbConnect, 'g')
        ->where('g.status', '!=', EntityStatus::statusIdDeleted)
        ->select('g.*')->selectSub($alumnsCountQuery, 'alumns_count');
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocSelectQuery($dbConnect);
        return $dbConnect->fetchAll($query, [self::class, 'row2Gym']);
    }

    static function queryListByOwnerId(DbConnector $dbConnect, int $ownerId):array{
        $query = self::allocSelectQuery($dbConnect)->where('g.owner_id', $ownerId);
        return $dbConnect->fetchAll($query, [self::class, 'row2Gym']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocSelectQuery($dbConnect)->where('g.id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Gym']);
    }

    static function queryBySlug(DbConnector $dbConnect, string $slug):?self{
        $query = self::allocSelectQuery($dbConnect)->where('g.slug', $slug);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Gym']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):?self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::validationError('No se encuentra gimnasio con id: '.$dbId);
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