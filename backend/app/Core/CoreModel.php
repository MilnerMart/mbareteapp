<?php
namespace App\Core;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\Resource;

class CoreModel{

    const exerciseModelId = 10, resourceModelId = 20, muscleModelId = 40;

    private const _modelInfoMap = [
        self::exerciseModel => [
            'slug'=>'exercise',
            'label'=>'Ejercicios',
            'class'=>Exercise::class
        ],
        self::resourceModelId => [
            'slug'=>'resources',
            'label'=>'Recursos',
            'class'=>Resource::class
        ],
        self::muscleModelId => [
            'slug'=>'muscle',
            'label'=>'Musculos',
            'class'=>Muscle::class
        ]
    ];

    
}