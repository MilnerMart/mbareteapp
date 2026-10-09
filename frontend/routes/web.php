<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\GymController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'login'])->name('home');

// Imagenes subidas al backend, servidas desde el dominio del front
Route::get('/images/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media.show');

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

    // los entrenadores proponen musculos y ejercicios, el admin los revisa
    Route::middleware('frontend.catalog')->group(function () {
        Route::get('/muscle/create', [MuscleController::class, 'create'])->name('muscle.create');
        Route::post('/muscle', [MuscleController::class, 'store'])->name('muscle.store');
        Route::get('/muscle/{id}/exercise/create', [ExerciseController::class, 'create'])->name('exercise.create');
        Route::post('/exercise', [ExerciseController::class, 'store'])->name('exercise.store');
    });

    Route::middleware('frontend.admin')->group(function () {
        Route::get('/muscle/{id}/edit', [MuscleController::class, 'edit'])->whereNumber('id')->name('muscle.edit');
        Route::put('/muscle/{id}', [MuscleController::class, 'update'])->whereNumber('id')->name('muscle.update');
        Route::delete('/muscle/{id}', [MuscleController::class, 'destroy'])->whereNumber('id')->name('muscle.destroy');
        Route::get('/exercise/{id}/edit', [ExerciseController::class, 'edit'])->whereNumber('id')->name('exercise.edit');
        Route::put('/exercise/{id}', [ExerciseController::class, 'update'])->whereNumber('id')->name('exercise.update');
        Route::delete('/exercise/{id}', [ExerciseController::class, 'destroy'])->whereNumber('id')->name('exercise.destroy');
    });

    Route::middleware('frontend.member')->prefix('/my-gym')->group(function () {
        Route::get('/', [GymController::class, 'memberIndex'])->name('gym.member');
        Route::put('/{id}/visibility', [GymController::class, 'updateMemberVisibility'])->name('gym.member.visibility');
    });

    Route::get('/my-requests/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('ticket.show');
    Route::post('/my-requests/{id}/resubmit', [TicketController::class, 'resubmit'])->whereNumber('id')->name('ticket.resubmit');

    Route::prefix('/routine')->group(function () {
        Route::get('/', [RoutineController::class, 'index'])->name('routine.index');
        Route::get('/create', [RoutineController::class, 'create'])->name('routine.create');
        Route::post('/', [RoutineController::class, 'store'])->name('routine.store');
        Route::post('/exercise', [RoutineController::class, 'addExercise'])->name('routine.exercises.add');
        Route::get('/{id}', [RoutineController::class, 'show'])->whereNumber('id')->name('routine.show');
        Route::get('/{id}/edit', [RoutineController::class, 'edit'])->name('routine.edit');
        Route::put('/{id}', [RoutineController::class, 'update'])->name('routine.update');
        Route::delete('/{id}', [RoutineController::class, 'destroy'])->name('routine.destroy');
        Route::put('/{id}/exercises/{exerciseId}', [RoutineController::class, 'updateExercise'])->name('routine.exercises.update');
        Route::delete('/{id}/exercises/{exerciseId}', [RoutineController::class, 'removeExercise'])->name('routine.exercises.remove');
        Route::post('/assign', [RoutineController::class, 'assign'])->name('routine.assign');
        Route::delete('/{id}/assign/{userId}', [RoutineController::class, 'unassign'])->name('routine.unassign');
    });

    Route::middleware('frontend.gym')->prefix('/gym')->group(function () {
        Route::get('/', [GymController::class, 'index'])->name('gym.index');
        Route::get('/create', [GymController::class, 'create'])->name('gym.create');
        Route::post('/', [GymController::class, 'store'])->name('gym.store');
        Route::get('/{id}', [GymController::class, 'show'])->whereNumber('id')->name('gym.show');
        Route::get('/{id}/edit', [GymController::class, 'edit'])->name('gym.edit');
        Route::put('/{id}', [GymController::class, 'update'])->name('gym.update');
        Route::post('/{id}/image', [GymController::class, 'updateImage'])->name('gym.image');
        Route::delete('/{id}/users/{userId}', [GymController::class, 'removeUser'])->name('gym.users.remove');
    });

    Route::middleware('frontend.gym')->prefix('/tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('ticket.index');
        Route::post('/{id}/approve', [TicketController::class, 'approve'])->whereNumber('id')->name('ticket.approve');
        Route::post('/{id}/reject', [TicketController::class, 'reject'])->whereNumber('id')->name('ticket.reject');
    });
});
