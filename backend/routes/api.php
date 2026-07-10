<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function(){
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/muscle', [MuscleController::class, 'index']);
    Route::get('/muscle/{id}', [MuscleController::class, 'show']);
    Route::get('/exercise', [ExerciseController::class, 'index']);
    Route::get('/exercise/{id}', [ExerciseController::class, 'show']);
    Route::get('/exercise/group/{muscleId}', [ExerciseController::class, 'getExerciseGroup']);
    Route::get('/exercise/{id}/resource', [ExerciseController::class, 'getResources']);

    Route::middleware('auth:sanctum')->prefix('/auth')->group(function(){
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware('auth:sanctum')->group(function(){
        Route::post('/user/{id}/profile-image', [UserController::class, 'updateProfileImage']);
        Route::get('/user/{id}', [UserController::class, 'show']);

        Route::post('/muscle', [MuscleController::class, 'store']);
        Route::put('/muscle/{id}', [MuscleController::class, 'update']);
        Route::delete('/muscle/{id}', [MuscleController::class, 'destroy']);

        Route::post('/exercise', [ExerciseController::class, 'store']);
        Route::put('/exercise/{id}', [ExerciseController::class, 'update']);
        Route::delete('/exercise/{id}', [ExerciseController::class, 'destroy']);
        Route::post('/exercise/{id}/resource', [ExerciseController::class, 'addResource']);
    });
});
