<?php

use App\Http\Controllers\FollowupFeedbackController;
use App\Http\Controllers\FollowUpStatusController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
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
use App\Http\Controllers\ClientErrorLogController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\EventTransactionController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\PicnicRegistrationController;

// Refactor controllers
/////////////////////////////////////////////////////////////////////////
// Guest routes
Route::middleware('guest')->group(function () {
    Route::post('first-timers', [FirstTimerController::class, 'store']);
    Route::post('forms', [FormController::class, 'store']);
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/client-errors', [ClientErrorLogController::class, 'store']);
    Route::post('/mail/bulk', [MailController::class, 'sendBulkMail']);
});


// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // picnic
    Route::post('/picnic/register', [PicnicRegistrationController::class, 'register']);
    Route::get('/picnic/my-registration', [PicnicRegistrationController::class, 'myRegistration']);

    Route::apiResource('event-registrations', EventRegistrationController::class);
    Route::get('admin/event-registrations', [EventRegistrationController::class, 'adminIndex']);
    Route::get('registrations/{registration}/transactions', [EventTransactionController::class, 'index']);
    Route::post('registrations/{registration}/transactions', [EventTransactionController::class, 'store']);
    Route::patch('transactions/{transaction}', [EventTransactionController::class, 'update']);

    // Users (for leaders, admin and members)
    Route::put('/update-profile', [UserController::class, 'update']);
    Route::get('/leaders/absentees', [UserController::class, 'getAssignedAbsentees']);
    Route::get('/members/assigned', [UserController::class, 'getAssignedMembers']);
    // Follow-up statuses
    Route::apiResource('follow-up-statuses', FollowUpStatusController::class);
    // followup feedbacks
    Route::apiResource('followup-feedbacks', FollowupFeedbackController::class);

    Route::get('/first-timers/followup-feedbacks', [FollowupFeedbackController::class, 'getFirstTimersWithFollowups']);
    Route::get('/absent-members/followup-feedbacks', [FollowupFeedbackController::class, 'getAbsentMembersWithFollowups']);
    Route::get('/all-members/followup-feedbacks', [FollowupFeedbackController::class, 'getMembersWithFollowups']);
    Route::get('/members/{user}/followup-feedbacks', [FollowupFeedbackController::class, 'getFollowUpsByMember']);
    Route::get('/first-timers/{firstTimer}/followup-feedbacks', [FollowupFeedbackController::class, 'getFollowUpsByFirstTimer']);

    // First-timers
    Route::prefix('first-timers')
        ->controller(FirstTimerController::class)
        ->name('first-timers.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/assigned', 'getAssignedFirstTimers')->name('assigned');
            Route::post('/{firstTimer}/welcome-email', 'sendFirstTimerWelcomeEmail')->name('welcome-email');
            Route::get('/{firstTimer}', 'show')->name('show');
            Route::put('/{firstTimer}', 'update')->name('update');
        });
    //members
    Route::apiResource('members', MemberController::class);
    Route::post('members/delete', [MemberController::class, 'destroy']);
    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/mark', [AttendanceController::class, 'markAttendance']);
        Route::get('/history', [AttendanceController::class, 'history']);
        Route::get('/monthly-stats', [AttendanceController::class, 'getUserAttendanceMonthlyStats']);
    });
    //media
    Route::get('/media', [MediaController::class, 'index']);


    // leaders-only routes
    Route::prefix('leaders')
        ->middleware(['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::LEADER->value])
        ->group(function () {
            Route::apiResource('units', UnitController::class);
        });


    Route::prefix('admin')
        ->middleware("role:" . RoleEnum::ADMIN->value)
        ->group(function () {
            Route::get('/picnic/registrations', [PicnicRegistrationController::class, 'adminIndex']);
            // First-timers
            Route::get('first-timers/analytics', [FirstTimerController::class, 'getFirstTimersAnalytics']);
            // Members
            Route::get('/members/role/{role}', [MemberController::class, 'getMembersByRole']);
            // Forms
            Route::prefix('forms')->controller(FormController::class)->group(function () {
                Route::get('/', 'index');
                Route::delete('/', 'destroy');
                Route::patch('/completed', 'markAsCompleted');
            });
            // attendance
            Route::prefix('attendance')->group(function () {
                Route::get('/', [AttendanceController::class, 'index']);
                Route::post('/mark-absentees', [AttendanceController::class, 'markAbsentees']);
                Route::post('/badges/award', [AttendanceController::class, 'awardMonthlyBadges']);
                Route::post('/mark', [AttendanceController::class, 'adminMarkAttendance']);
                Route::post('/assign-absentees-to-leaders', [AttendanceController::class, 'assignAbsenteesToLeaders']);
                Route::get('/monthly-stats', [AttendanceController::class, 'getAdminAttendanceMonthlyStats']);
            });
            Route::post('/media/fetch', [MediaController::class, 'fetchFromYouTube']);
        });
});










/////////////////////////////////////////////////////////////////////////

// -----------------------------------------
// Public routes
// -----------------------------------------
Route::get('/test', [TestController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthController::class, 'reset']);

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
        Route::get('/core-app-data', [ServiceController::class, 'fetchCoreAppData']);
    });


    // -----------------------------------------
    // Admin-only
    // -----------------------------------------
    Route::middleware(['role:' . RoleEnum::ADMIN->value])->prefix('admin')->group(function () {

        Route::get('/analytics', [AdminController::class, 'getAdminAnalytics']);
        Route::post('/assign-role', [AdminController::class, 'assignRoleToUsers']);
        // Usher Attendance
        Route::apiResource('usher-attendance', UsherAttendanceController::class);
    });
});
