<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Db\DbConnector;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Routine extends BaseEntity {
    use HasFactory;

    private string $name, $slug;

    private int $frecuency, $restTime, $ownerId;

    private const myTable = DbSchema::tableExercise;
            
    public function allocNew(string $name, string $slug, int $ownerId, int $frecuency, $restTime):self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->ownerId = $ownerId;
        $self->frecuency = $frecuency;
        $self->restTime = $restTime;
        
        return $self;
    }   
    
    function getModelId():int{
        return CoreModel::resourceModelId;
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    protected function casts(): array
    {
        return [
            'data' =>'array'
        ];
    }
}
