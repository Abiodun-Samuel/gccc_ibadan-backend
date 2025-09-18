<?php

use App\Enums\UserRole;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\FirstTimerController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

//test
Route::get('/test', [TestController::class, 'index']);


Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/admin/users/bulk-register', [AuthController::class, 'bulkRegister']);
Route::get('/services', [ServiceController::class, 'index']);
Route::post('/first-timers', [FirstTimerController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // attendance
    Route::post('/attendance/mark', [AttendanceController::class, 'markAttendance']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);
    // service
    Route::get('/services/today', [ServiceController::class, 'today']);
    //first timer
    Route::post('first-timers/{first_timer}/assign', [FirstTimerController::class, 'assignFollowup']);
    Route::post('first-timers/{first_timer}/unassign', [FirstTimerController::class, 'unassignFollowup']);
    Route::post('first-timers/{first_timer}/status', [FirstTimerController::class, 'setFollowupStatus']);
    Route::apiResource('first-timers', FirstTimerController::class)->except(['store']);
    // admins only
    Route::middleware(['role:' . UserRole::ADMIN->value . '|' . UserRole::SUPER_ADMIN->value])->group(function () {
        // first timer
        Route::get('first-timers/analytics', [FirstTimerController::class, 'analytics']);
        // members
        Route::apiResource('members', MemberController::class);
        // Route::post('/members/bulk-upsert', [MemberController::class, 'bulkUpsert']);
        //attendance
        Route::post('/attendance/admin-mark-attendance', [AttendanceController::class, 'adminMarkAttendance']);
        // attendance
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance/absentees', [AttendanceController::class, 'getAbsentees']);
        Route::post('/attendance/mark-absentees', [AttendanceController::class, 'markAbsentees']);
    });
});
