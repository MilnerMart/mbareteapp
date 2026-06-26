<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class Muscle extends Model
{
    use HasFactory;

    public static function allocMuscle(string $name, string $slug, string $description,int $restDays, string $image): self{
        $self = new self();
        $self->name = $name;
        $self->slug = $slug;
        $self->description = $description;
        $self->recommended_rest_days = $restDays;
        $self->image_url = $image;
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

    function setRestDays(int $restDays): void{
        $this->recommended_rest_days = $restDays;
    }

    function getRestDays(): string{
        return $this->slug;
    }

    function setImg(string $image){
        $this->image_url = $image;
    }
    
    function getImg(){
        return $this->image_url;
    }

    function setDescription(string $description){
        $this->description = $description;
    }

    function getDescription(){
        return $this->description;
    }
}
