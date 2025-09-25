<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\PermissionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FirstTimerController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\UsherAttendanceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TestController;
use App\Enums\RoleEnum;

// -----------------------------------------
// Public routes
// -----------------------------------------
Route::get('/test', [TestController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/admin/users/bulk-register', [AuthController::class, 'bulkRegister']);

Route::get('/services', [ServiceController::class, 'index']);
Route::post('/first-timers', [FirstTimerController::class, 'store']);
Route::post('/forms', [FormController::class, 'store']);

// -----------------------------------------
// Authenticated routes
// -----------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Service
    Route::prefix('services')->group(function () {
        Route::get('/today-service', [ServiceController::class, 'today']);
    });


    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/mark', [AttendanceController::class, 'markAttendance']);
        Route::get('/history', [AttendanceController::class, 'history']);
    });

    // First Timers (user-facing actions)
    Route::prefix('first-timers')->group(function () {
        Route::post('{first_timer}/assign', [FirstTimerController::class, 'assignFollowup']);
        Route::post('{first_timer}/unassign', [FirstTimerController::class, 'unassignFollowup']);
        Route::post('{first_timer}/status', [FirstTimerController::class, 'setFollowupStatus']);
    });
    Route::apiResource('first-timers', FirstTimerController::class)->except('store');

    // Member dashboard analytics
    Route::get('member/analytics', [MemberController::class, 'getAnalytics']);

    // -----------------------------------------
    // Admin-only routes
    // -----------------------------------------
    Route::middleware(['role:' . RoleEnum::ADMIN->value])->prefix('admin')->group(function () {
        //permision
        Route::post('users/{user}/roles', [AdminUserController::class, 'assignRoles']);
        Route::post('users/{user}/permissions', [AdminUserController::class, 'assignPermissions']);
        Route::apiResource('permissions', PermissionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

        // Attendance management
        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('/mark', [AttendanceController::class, 'adminMarkAttendance']);
            Route::post('/absentees', [AttendanceController::class, 'getAbsentees']);
            Route::post('/mark-absentees', [AttendanceController::class, 'markAbsentees']);
            Route::get('/attendance-analytics', [AttendanceController::class, 'attendanceAnalytics']);
        });

        // Usher Attendance
        Route::apiResource('usher-attendance', UsherAttendanceController::class);

        // Units management
        Route::prefix('units/')->group(function () {
            Route::post('/assign-member', [AdminController::class, 'assignMemberToUnit']);
            Route::post('/unassign-member', [AdminController::class, 'unassignMemberFromUnit']);
            Route::post('/assign-leader', [AdminController::class, 'assignLeaderOrAssistantToUnit']);
            Route::post('/unassign-leader', [AdminController::class, 'unassignLeaderOrAssistantFromUnit']);
        });

        // Forms management
        Route::apiResource('forms', FormController::class)->except('store');

        // First-timers analytics
        Route::get('/first-timers/analytics', [AdminController::class, 'getFirstTimersAnalytics']);

        // General analytics
        Route::get('/analytics', [AdminController::class, 'getAdminAnalytics']);

        // Members management
        Route::prefix('members')->group(function () {
            Route::post('/bulk-create', [MemberController::class, 'bulkCreate'])->name('members.bulk-create');
            Route::put('/bulk-update', [MemberController::class, 'bulkUpdate'])->name('members.bulk-update');
        });
        Route::apiResource('members', MemberController::class);
    });
});
