<?php

use App\Http\Controllers\Admin\AbsenceReviewController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Parent\PermissionController as ParentPermissionController;
use App\Http\Controllers\StudentAffairs\ReviewController;
use App\Http\Controllers\Teacher\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// ------------------------------------------------------------------ Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ------------------------------------------------------------- Authenticated
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --------------------------------------------------------------- Admin
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('permissions', [AdminPermissionController::class, 'index'])->name('permissions.index');
            Route::get('permissions/create', [AdminPermissionController::class, 'create'])->name('permissions.create');
            Route::post('permissions', [AdminPermissionController::class, 'store'])->name('permissions.store');
            Route::get('permissions/{permission}', [AdminPermissionController::class, 'show'])->name('permissions.show');
            Route::post('permissions/{permission}/approve', [AdminPermissionController::class, 'approve'])->name('permissions.approve');
            Route::post('permissions/{permission}/reject', [AdminPermissionController::class, 'reject'])->name('permissions.reject');

            Route::get('absence', [AbsenceReviewController::class, 'index'])->name('absence.index');
            Route::get('absence/{attendance}', [AbsenceReviewController::class, 'show'])->name('absence.show');
            Route::post('absence/{attendance}/decide', [AbsenceReviewController::class, 'decide'])->name('absence.decide');

            Route::get('students', [StudentController::class, 'index'])->name('students.index');
            Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
            Route::post('students', [StudentController::class, 'store'])->name('students.store');
            Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');

            Route::get('classes', [ClassController::class, 'index'])->name('classes.index');
            Route::get('classes/create', [ClassController::class, 'create'])->name('classes.create');
            Route::post('classes', [ClassController::class, 'store'])->name('classes.store');
            Route::get('classes/{class}', [ClassController::class, 'show'])->name('classes.show');

            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        });

    // -------------------------------------------------------------- Teacher
    Route::prefix('teacher')
        ->name('teacher.')
        ->middleware('role:teacher')
        ->group(function () {
            Route::get('attendance', [AttendanceController::class, 'history'])->name('attendance.history');
            Route::post('classes/{class}/open', [AttendanceController::class, 'open'])->name('attendance.open');
            Route::get('attendance/{session}', [AttendanceController::class, 'mark'])->name('attendance.mark');
            Route::post('attendance/{session}/save', [AttendanceController::class, 'save'])->name('attendance.save');
            Route::post('attendance/{session}/submit', [AttendanceController::class, 'submit'])->name('attendance.submit');
        });

    // ------------------------------------------------------ Student Affairs
    Route::prefix('student-affairs')
        ->name('student-affairs.')
        ->middleware('role:student_affairs')
        ->group(function () {
            Route::get('review', [ReviewController::class, 'index'])->name('review.index');
            Route::post('review/{attendance}/arrived', [ReviewController::class, 'markArrived'])->name('review.arrived');
            Route::post('review/{attendance}/escalate', [ReviewController::class, 'escalate'])->name('review.escalate');
        });

    // ---------------------------------------------------------------- Parent
    Route::prefix('parent')
        ->name('parent.')
        ->middleware('role:parent')
        ->group(function () {
            Route::get('permissions', [ParentPermissionController::class, 'index'])->name('permissions.index');
            Route::get('permissions/create', [ParentPermissionController::class, 'create'])->name('permissions.create');
            Route::post('permissions', [ParentPermissionController::class, 'store'])->name('permissions.store');
        });
});
