<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ParentApiController;
use App\Http\Controllers\Api\V1\TeacherApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public authentication endpoint
    Route::post('/login', [AuthController::class, 'login'])->name('api.v1.login');

    // Authenticated API routes via Laravel Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'me'])->name('api.v1.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.logout');

        // Teacher Tablet endpoints
        Route::prefix('teacher')->group(function () {
            Route::get('/classes', [TeacherApiController::class, 'classes'])->name('api.v1.teacher.classes');
            Route::get('/classes/{classId}/students', [TeacherApiController::class, 'students'])->name('api.v1.teacher.students');
            Route::get('/sessions', [TeacherApiController::class, 'sessions'])->name('api.v1.teacher.sessions');
            Route::post('/sessions/{sessionId}/mark', [TeacherApiController::class, 'markSession'])->name('api.v1.teacher.mark');
        });

        // Parent Mobile endpoints
        Route::prefix('parent')->group(function () {
            Route::get('/children', [ParentApiController::class, 'children'])->name('api.v1.parent.children');
            Route::get('/children/{studentId}/attendance', [ParentApiController::class, 'childAttendance'])->name('api.v1.parent.child-attendance');
            Route::post('/permissions', [ParentApiController::class, 'submitPermission'])->name('api.v1.parent.permissions.submit');
        });
    });
});
