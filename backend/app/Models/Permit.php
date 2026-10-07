<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\HasDbData;
use App\Helpers\BaseHelper;
use App\Models\BaseEntity;
use App\PublicException;
use Illuminate\Database\Query\Builder;
use stdClass;

class Permit extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tablePermits;

    const seeAllPermitSlug = 'the-one-who-sees-all', createGymEntityPermitSlug = 'create-gym-entity';

    private string $name, $slug;

    private ?int $roleId; //permito nullable para mas adelante hacer como otp interno, para que no este si o si ligado a un role el permiso
    // tipo x usuario necesita x permiso a veces, entonces pide, se asigana y quema.
    
    public static function allocNew(string $name, string $slug, int $roleId):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->roleId = $roleId;
        return $self;
    }

    function getModelId(): int{
        return CoreModel::roleModelId;
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

    function getRoleId(): int{
        return $this->roleId;
    }

    private function permit2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->role_id = $this->roleId;
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2Permit(DbAdapter $dbAdapter, stdClass $row):self{
        $permit = new Permit();
        $permit->initFromDbRow($dbAdapter, $row);
        $permit->name = $row->name;
        $permit->slug = $row->slug;
        $permit->roleId = $row->role_id;
        $permit->_loadDataFromDb( $row->data );
        return $permit;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' =>  $this->getName(),
            'slug' =>  $this->getSlug(),
            'roleId' =>  $this->getRoleId(),
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Permit']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocDbTable($dbConnect)->where('id', $dbId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchSingle($query, [self::class ,'row2Permit']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):?self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::validationError('No se encuentra permiso con id: '.$dbId);
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
        $writeArray = (array)$this->permit2Row ($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }

}