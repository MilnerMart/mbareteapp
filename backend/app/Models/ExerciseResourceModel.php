<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseResourceModel extends Model
{
    protected $table = 'exercise_resources';
    
    public const kindImg = 1, kindVideo= 5, kindGif= 11;
    public static function allocNew(string $name,  int $kind, string $url,  int $exerciseId, int $statusId){
        $self = new self();
        $self->name = $name;
        $self->kind = $kind;
        $self->url = $url;
        $self->status = $statusId;
        $self->exercise_id = $exerciseId;
        return $self;
    }

    public static function kindMap(int $kindId){
        $kind = self::kindMap[$kindId];
        return $kind ?? null;
    }

    private const kindMap = [
        self::kindGif => 'Gif',
        self::kindImg => 'Image',
        self::kindVideo => 'Video',
    ];
}
