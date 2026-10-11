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
use App\ServiceApi\ProviderClientRegistry;
use Illuminate\Database\Query\Builder;
use stdClass;


class Provider extends BaseEntity {

    use HasDbData;

    private const myTable = DbSchema::tableProviders;

    private string $name, $slug, $type;

    private ?string $baseUrl = null;

    public static function allocNew(string $name, string $slug, string $type, ?string $baseUrl = null):self{
        ProviderClientRegistry::clientClassOrFail($type);
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->type = $type;
        $self->baseUrl = $baseUrl;
        return $self;
    }

    function getModelId(): int{
        return CoreModel::providerModelId;
    }

    function getName(): string{
        return $this->name;
    }

    function setName(string $name): void{
        $this->name = $name;
    }

    function getSlug(): string{
        return $this->slug;
    }

    function getType(): string{
        return $this->type;
    }

    function getBaseUrl(): ?string{
        return $this->baseUrl;
    }

    function setBaseUrl(?string $baseUrl): void{
        $this->baseUrl = $baseUrl ?: null;
    }

    private function provider2Row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->type = $this->type;
        $row->base_url = $this->baseUrl;
        $row->data = $this->_encodeDataForDb();
        return $row;
    }

    static function row2Provider(DbAdapter $dbAdapter, stdClass $row):self{
        $provider = new Provider();
        $provider->initFromDbRow($dbAdapter, $row);
        $provider->name = $row->name;
        $provider->slug = $row->slug;
        $provider->type = (string)$row->type;
        $provider->baseUrl = $row->base_url;
        $provider->_loadDataFromDb($row->data);
        return $provider;
    }

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'baseUrl' => $this->baseUrl,
            'status' => $this->getStatusId(),
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    private static function allocSelectQuery(DbConnector $dbConnect): Builder{
        return self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select()->orderBy('name');
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocSelectQuery($dbConnect);
        return $dbConnect->fetchAll($query, [self::class, 'row2Provider']);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = self::allocSelectQuery($dbConnect)->where('id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class, 'row2Provider']);
    }

    static function queryByDbIdOrFail(DbConnector $dbConnect, int $dbId):self{
        $self = self::queryByDbId($dbConnect, $dbId);
        if(!$self){
            throw PublicException::notFoundError('No se encuentra el proveedor con id: '.$dbId);
        }
        return $self;
    }

    static function queryBySlug(DbConnector $dbConnect, string $slug):?self{
        $query = self::allocSelectQuery($dbConnect)->where('slug', $slug);
        return $dbConnect->fetchSingle($query, [self::class, 'row2Provider']);
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->provider2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }
    }

}
