<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use \Illuminate\Database\Query\Builder;
use stdClass;

class Resource extends BaseEntity {

    private const myTable = DbSchema::tableResources;
    
    
    public const kindImg = 1, kindVideo= 5, kindGif= 11;

    private string $name, $url, $slug;

    private int $kind, $status, $ownerId, $modelId;


    public static function allocNew(string $name, string $slug,  int $kind, int $modelId, int $ownerId, string $url, int $statusId):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->modelId = $modelId;
        $self->ownerId = $ownerId;
        $self->kind = $kind;
        $self->url = $url;
        $self->status = $statusId;
        return $self;
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

    function getUrl():string{
        return $this->url;
    }
    function getModelId():int{
        return CoreModel::resourceModelId;
    }

    public static function kindMap(int $kindId):string{
        return self::kindMap[$kindId] ?? null;
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    private const kindMap = [
        self::kindGif => 'Gif',
        self::kindImg => 'Image',
        self::kindVideo => 'Video',
    ];

    public function buildApiModel(): array{
        return [
            'id' => $this->getEntityId(),
            'name' =>  $this->getName(),
            'slug' =>  $this->getSlug(),
            'url' => $this->url,
        ];
    }

    static function row2Resource(DbAdapter $dbAdapter, stdClass $row):self{
        $resource = new Resource();
        $resource->initFromDbRow($dbAdapter, $row);
        $resource->name = $row->name;
        $resource->slug = $row->slug;
        $resource->modelId = $row->model_id;
        $resource->ownerId = $row->owner_id;
        $resource->kind = $row->kind;
        $resource->url = $row->url;
        $resource->status = $row->status;
        return $resource;
    }

    private function resource2row(DbAdapter $dbAdapter): stdClass{
        $row = $this->allocDbRow($dbAdapter);
        $row->name = $this->name;
        $row->slug = $this->slug;
        $row->model_id = $this->modelId; 
        $row->owner_id = $this->ownerId;
        $row->kind = $this->kind;
        $row->url = $this->url ;
        $row->status = $this->status;
        return $row;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = Self::allocDbTable($dbConnect)->where('id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Resource']);
    }

    static function queryByOwnerAndModelId(DbConnector $dbConnect, int $modelId, int $muscleId):?self{
        $query = Self::allocDbTable($dbConnect)->where('model_id', $modelId)
        ->where('owner_id', $muscleId);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Resource']);
    }

    static function queryList(DbConnector $dbConnect):array{
        $query = self::allocDbTable($dbConnect)->where('status', '!=', EntityStatus::statusIdDeleted)->select();
        return $dbConnect->fetchAll($query, [self::class, 'row2Resource']);
    }

    public function writeToDb(DbConnector $dbConnector): void{
        $now = BaseHelper::utcNow();
        $this->setUpdatedTime( $now );
        if( !$this->getCreatedTime() ){
            $this->setCreatedTime( $now );
        }

        $writable = self::allocDbTable($dbConnector);
        $writeArray = (array)$this->resource2Row($dbConnector);
        $this->isReadyForDbOrFail();
        if ($this->getEntityId()) {
            $notDeletedCond =  ['id' => $this->getEntityId()];
            $writable->where( $notDeletedCond )->update( $writeArray);
        } else {
            $this->setEntityId( $writable->insertGetId($writeArray) );
        }

    }
    
}
