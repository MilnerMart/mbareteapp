<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class Exercise extends Model
{
    use HasFactory;

    public static function allocNew(string $name, string $slug, int $muscleId, int $restTime, ?string $description = null, ?string $imageUrl = null, ?string $videoUrl = null, ?string $gifUrl = null){
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->muscle_id = $muscleId;
        $self->description = $description;
        $self->recommended_rest_time = $restTime;
        $self->image = $imageUrl;
        $self->video = $videoUrl;
        $self->gif = $gifUrl;
        return $self;
    }
}
