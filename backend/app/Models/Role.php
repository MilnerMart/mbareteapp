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

class Role extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableRoles;

    // trainer-role bloqueado temporalmente: los entrenadores nuevos no pueden registrarse solos.
    public const publicRegisterSlugs = ['trainee-role'];

    private string $name, $slug;
    
    public static function allocNew(string $name, string $slug):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;

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

    function setRolePermits( array $permitsArr): void{
        $this->_setDataItem('rolePermits', $permitsArr);
    }

    function getRolePermits():?array{
        return $this->_getArrayDataItem('rolePermits') ?? null;
    }

    private function role2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2Role(DbAdapter $dbAdapter, stdClass $row):self{
        $role = new Role();
        $role->initFromDbRow($dbAdapter, $row);
        $role->name = $row->name;
        $role->slug = $row->slug;
        $role->_loadDataFromDb( $row->data );
        return $role;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' =>  $this->getName(),
            'slug' =>  $this->getSlug(),
            'permits' => $this->getRolePermits()
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Role']);
    }

    static function queryListByUserId(DbConnector $dbConnect, int $userId):array{
        $query = self::allocDbTable($dbConnect, 'r')
        ->join(DbSchema::tableUserRoles.' as ur', 'ur.role_id', '=', 'r.id')
        ->where('ur.user_id', $userId)
        ->where('r.status', '!=', EntityStatus::statusIdDeleted)->select('r.*');
        return $dbConnect->fetchAll($query, [self::class, 'row2Role']);
    }

    static function queryPublicRegisterList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->whereIn('slug', self::publicRegisterSlugs)
        ->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Role']);
    }

    function isPublicRegisterRole():bool{
        return in_array($this->slug, self::publicRegisterSlugs, true);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocDbTable($dbConnect)->where('id', $dbId)
        ->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchSingle($query, [self::class ,'row2Role']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):?self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::validationError('No se encuentra rol con id: '.$dbId);
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
        $writeArray = (array)$this->role2Row ($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }

}