<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\GymController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'login'])->name('home');

Route::get('/login', [AuthController::class, 'login'])->name('user.login');

Route::post('/login', [AuthController::class, 'authenticate'])->name('user.login.submit');

Route::get('/register', [AuthController::class, 'register'])->name('user.register');

Route::post('/register', [AuthController::class, 'store'])->name('user.register.submit');

Route::get('/muscle', [MuscleController::class, 'index'])->name('muscle.index');

Route::get('/exercise', [ExerciseController::class, 'index'])->name('exercise.index');

Route::get('/exercise/{id}', [ExerciseController::class, 'getExerciseGroup'])->name('exercise.group');

Route::get('/exercise/{id}/resource', [ExerciseController::class, 'getExerciseResource'])->name('exercise.resource');

Route::middleware('frontend.auth')->group(function () {
    Route::get('/profile/{id}', [UserController::class, 'getProfile'])->name('user.profile');

    Route::post('/profile/{id}/image', [UserController::class, 'updateProfileImage'])->name('user.profile.image');

    Route::post('/logout', [AuthController::class, 'logout'])->name('user.logout');

    Route::middleware('frontend.gym')->prefix('/gym')->group(function () {
        Route::get('/', [GymController::class, 'index'])->name('gym.index');
        Route::get('/create', [GymController::class, 'create'])->name('gym.create');
        Route::post('/', [GymController::class, 'store'])->name('gym.store');
        Route::get('/{id}/edit', [GymController::class, 'edit'])->name('gym.edit');
        Route::put('/{id}', [GymController::class, 'update'])->name('gym.update');
    });
});
