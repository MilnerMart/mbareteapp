<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function(){
    Route::resource('/muscle', MuscleController::class);
    Route::resource('/exercise', ExerciseController::class);
    Route::resource('/user', UserController::class);
    Route::get('/exercise/group/{muscleId}', [ExerciseController::class, 'getExerciseGroup']);
    Route::get('/exercise/{id}/resource', [ExerciseController::class, 'getResources']);
    Route::post('/exercise/{id}/resource', [ExerciseController::class, 'addResource']);
});