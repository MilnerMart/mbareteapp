<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\GymEntityController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PublicResourceController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function(){
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/auth/register/roles', [AuthController::class, 'registerRoles']);
    Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:email-verification-verify');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset-send');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

    Route::get('/muscle', [MuscleController::class, 'index']);
    Route::get('/muscle/{id}', [MuscleController::class, 'show']);
    Route::get('/exercise', [ExerciseController::class, 'index']);
    Route::get('/exercise/{id}', [ExerciseController::class, 'show']);
    Route::get('/exercise/group/{muscleId}', [ExerciseController::class, 'getExerciseGroup']);
    Route::get('/exercise/{id}/resource', [ExerciseController::class, 'getResources']);

    Route::middleware('auth:sanctum')->prefix('/auth')->group(function(){
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])->middleware('throttle:email-verification-send');
    });

    Route::middleware('auth:sanctum')->group(function(){
        Route::post('/user/{id}/profile-image', [UserController::class, 'updateProfileImage']);
        Route::put('/user/{id}/profile', [UserController::class, 'updateProfile']);
        Route::get('/user/{id}', [UserController::class, 'show']);
        Route::get('/user/{id}/detail', [UserController::class, 'detail']);
        Route::get('/user/{id}/logs', [UserController::class, 'logs']);

        Route::post('/muscle', [MuscleController::class, 'store']);
        Route::put('/muscle/{id}', [MuscleController::class, 'update']);
        Route::delete('/muscle/{id}', [MuscleController::class, 'destroy']);

        Route::post('/exercise', [ExerciseController::class, 'store']);
        Route::put('/exercise/{id}', [ExerciseController::class, 'update']);
        Route::delete('/exercise/{id}', [ExerciseController::class, 'destroy']);
        Route::post('/exercise/{id}/resource', [ExerciseController::class, 'addResource']);
        Route::delete('/exercise/{id}/resource/{resourceId}', [ExerciseController::class, 'removeResource']);

        Route::get('/public-resources/provider', [PublicResourceController::class, 'provider']);
        Route::get('/public-resources', [PublicResourceController::class, 'search']);
        Route::get('/public-resources/{itemKey}/media', [PublicResourceController::class, 'media']);

        Route::prefix('resources')->group(function(){
            Route::get('/', [ResourceController::class, 'index']);
            Route::post('/', [ResourceController::class, 'store']);
            Route::get('/{id}', [ResourceController::class, 'show']);
        });

        Route::get('/me/gyms', [GymEntityController::class, 'memberIndex']);
        Route::post('/me/gyms/join', [GymEntityController::class, 'requestJoin']);
        Route::put('/me/gyms/{id}/visibility', [GymEntityController::class, 'updateMemberVisibility']);
        Route::get('/me/tickets', [TicketController::class, 'myIndex']);
        Route::get('/me/calendar', [CalendarController::class, 'index']);
        Route::post('/me/calendar/notes', [CalendarController::class, 'storeNote']);
        Route::put('/me/calendar/notes/{id}', [CalendarController::class, 'updateNote']);
        Route::delete('/me/calendar/notes/{id}', [CalendarController::class, 'destroyNote']);
        Route::get('/me/notifications', [NotificationController::class, 'index']);
        Route::put('/me/notifications/read', [NotificationController::class, 'markRead']);

        Route::prefix('tickets')->group(function(){
            Route::get('/', [TicketController::class, 'index']);
            Route::get('/{id}', [TicketController::class, 'show']);
            Route::post('/{id}/approve', [TicketController::class, 'approve']);
            Route::post('/{id}/reject', [TicketController::class, 'reject']);
            Route::post('/{id}/resubmit', [TicketController::class, 'resubmit']);
        });

        Route::prefix('entities')->group(function(){
            Route::post('/gym/', [GymEntityController::class, 'store']);
            Route::get('/gym/', [GymEntityController::class, 'index']);
            Route::get('/gym/{id}', [GymEntityController::class, 'show']);
            Route::put('/gym/{id}', [GymEntityController::class, 'update']);
            Route::post('/gym/{id}/image', [GymEntityController::class, 'updateImage']);
            Route::get('/gym/{id}/users', [GymEntityController::class, 'users']);
            Route::delete('/gym/{id}/users/{userId}', [GymEntityController::class, 'removeUser']);
        });

        Route::prefix('routines')->group(function(){
            Route::get('/', [RoutineController::class, 'index']);
            Route::post('/', [RoutineController::class, 'store']);
            Route::get('/{id}', [RoutineController::class, 'show']);
            Route::put('/{id}', [RoutineController::class, 'update']);
            Route::delete('/{id}', [RoutineController::class, 'destroy']);
            Route::post('/{id}/exercises', [RoutineController::class, 'addExercise']);
            Route::put('/{id}/done', [RoutineController::class, 'syncDoneToday']);
            Route::delete('/{id}/exercises/{exerciseId}', [RoutineController::class, 'removeExercise']);
            Route::post('/{id}/assign', [RoutineController::class, 'assign']);
            Route::delete('/{id}/assign/{userId}', [RoutineController::class, 'unassign']);
        });

        Route::prefix('roles')->group(function(){
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
        });

        Route::prefix('config')->group(function(){
            Route::get('/provider-types', [ConfigController::class, 'providerTypeIndex']);
            Route::get('/providers', [ConfigController::class, 'providerIndex']);
            Route::post('/providers', [ConfigController::class, 'providerStore']);
            Route::get('/providers/{id}', [ConfigController::class, 'providerShow']);
            Route::put('/providers/{id}', [ConfigController::class, 'providerUpdate']);
            Route::delete('/providers/{id}', [ConfigController::class, 'providerDestroy']);
            Route::post('/providers/{id}/configs', [ConfigController::class, 'providerConfigStore']);
            Route::delete('/provider-configs/{id}', [ConfigController::class, 'providerConfigDestroy']);
            Route::get('/core', [ConfigController::class, 'coreIndex']);
            Route::put('/core', [ConfigController::class, 'coreUpdate']);
        });

        Route::prefix('permits')->group(function(){
            Route::get('/', [PermitController::class, 'index']);
            Route::post('/', [PermitController::class, 'store']);
        });
    });
});
