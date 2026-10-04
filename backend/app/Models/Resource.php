<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use BaseEntity;
use \Illuminate\Database\Query\Builder;
use stdClass;

class Resource extends BaseEntity {

    private const myTable = DbSchema::tableResources;
    
    
    public const kindImg = 1, kindVideo= 5, kindGif= 11;
    public static function allocNew(string $name,  int $kind, string $url,  int $exerciseId, int $statusId):self{
        $self = new self();
        $self->name = $name;
        $self->kind = $kind;
        $self->url = $url;
        $self->status = $statusId;
        $self->exercise_id = $exerciseId;
        return $self;
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

    static function row2Resource(DbAdapter $dbAdapter, stdClass $row):self{
        $resource = new Resource();
        return $resource;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    static function queryByDbId(DbConnector $dbConnect, ?int $dbId):?self{
        $query = Self::allocDbTable($dbConnect)->where('id', $dbId);
        return $dbConnect->fetchSingle($query, [self::class ,'row2Resource']);
    }
}
