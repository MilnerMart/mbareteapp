<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]

class Routine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'data' =>'array'
        ];
    }
}
