<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\FollowUpStatusController;
use App\Http\Controllers\PermissionController;
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

// Refactor controllers
/////////////////////////////////////////////////////////////////////////
// Guest routes
Route::middleware('guest')->group(function () {
    Route::post('first-timers', [FirstTimerController::class, 'store']);
    Route::post('forms', [FormController::class, 'store']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Follow-up statuses
    Route::apiResource('follow-up-statuses', FollowUpStatusController::class);

    // First-timers
    Route::prefix('first-timers')->controller(FirstTimerController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('status', 'setFollowupStatus');
    });

    // Admin-only routes
    Route::prefix('admin')
        ->middleware("role:" . RoleEnum::ADMIN->value)
        ->group(function () {
            // First-timers
            Route::get('first-timers/analytics', [FirstTimerController::class, 'getFirstTimersAnalytics']);
            // Forms
            Route::prefix('forms')->controller(FormController::class)->group(function () {
                Route::get('/', 'index');
                Route::put('/completed', 'markCompleted');
                Route::delete('{form}', 'destroy');
            });
        });
});










/////////////////////////////////////////////////////////////////////////

// -----------------------------------------
// Public routes
// -----------------------------------------
Route::get('/test', [TestController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/admin/users/bulk-register', [AuthController::class, 'bulkRegister']);

Route::get('/services', [ServiceController::class, 'index']);
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

    // Member dashboard analytics
    Route::get('member/analytics', [MemberController::class, 'getAnalytics']);

    // -----------------------------------------
    // Admin-only routes
    // -----------------------------------------
    Route::middleware(['role:' . RoleEnum::ADMIN->value])->prefix('admin')->group(function () {

        Route::get('/analytics', [AdminController::class, 'getAdminAnalytics']);
        Route::get('/attendance/monthly-stats', [AttendanceController::class, 'getAdminAttendanceMonthlyStats']);
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

        // Members management
        Route::prefix('members')->group(function () {
            Route::post('/bulk-create', [MemberController::class, 'bulkCreate'])->name('members.bulk-create');
            Route::put('/bulk-update', [MemberController::class, 'bulkUpdate'])->name('members.bulk-update');
        });
        Route::apiResource('members', MemberController::class);
    });
});
