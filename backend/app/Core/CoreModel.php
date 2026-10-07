<?php
namespace App\Core;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\Muscle;
use App\Models\Resource;
use App\Models\Role;

class CoreModel{

    const exerciseModelId = 10, resourceModelId = 20, muscleModelId = 40, gymEntityModelId = 50, roleModelId = 60, routineModelId = 70;

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
        ],
        self::gymEntityModelId => [
            'slug'=>'Gym',
            'label'=>'Gimnasio',
            'class'=>Gym::class
        ],
        self::roleModelId => [
            'slug'=>'role',
            'label'=>'Roles de usuario',
            'class'=>Role::class
        ]
    ];

    
}