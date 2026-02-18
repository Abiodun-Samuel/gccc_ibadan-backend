<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientErrorLogController;
use App\Http\Controllers\FirstTimerController;
use App\Http\Controllers\FollowupFeedbackController;
use App\Http\Controllers\FollowUpStatusController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UsherAttendanceController;
use App\Enums\RoleEnum;
use App\Http\Controllers\EventController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================================================

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('reset-password');
});

Route::middleware('guest')->group(function () {

    Route::prefix('event-registrations')->group(function () {
        Route::get('/', [RegistrationController::class, 'index']);
        Route::post('/', [RegistrationController::class, 'store']);
    });

    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/upcoming', [EventController::class, 'upcoming']);
        Route::get('/{id}', [EventController::class, 'show']);
    });    // Get si
    // Guest First Timer Registration
    Route::post('/first-timers', [FirstTimerController::class, 'store'])->name('first-timers.guest.store');
    // Guest Form Submission
    Route::post('/forms', [FormController::class, 'store'])->name('forms.guest.store');
    // Public Services
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    // Client Error Logging
    Route::post('/client-errors', [ClientErrorLogController::class, 'store'])->name('client-errors.store');
});

// ============================================================================
// AUTHENTICATED ROUTES (Require Authentication)
// ============================================================================

Route::middleware('auth:sanctum')->group(function () {
    // ------------------------------------------------------------------------
    // Authentication & User Profile
    // ------------------------------------------------------------------------
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::put('/update', [UserController::class, 'update'])->name('update');
    });

    // Alternative route for backward compatibility
    Route::put('/update-profile', [UserController::class, 'update'])->name('user.update-profile');

    // ------------------------------------------------------------------------
    // Services
    // ------------------------------------------------------------------------
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/today-service', [ServiceController::class, 'today'])->name('today');
        Route::get('/core-app-data', [ServiceController::class, 'fetchCoreAppData'])->name('core-app-data');
    });

    // ------------------------------------------------------------------------
    // Media
    // ------------------------------------------------------------------------
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');

    // ------------------------------------------------------------------------
    // Follow-Up System
    // ------------------------------------------------------------------------
    Route::prefix('follow-up-statuses')->name('follow-up-statuses.')->group(function () {
        Route::get('/', [FollowUpStatusController::class, 'index'])->name('index');
        Route::post('/', [FollowUpStatusController::class, 'store'])->name('store');
        Route::get('/{followUpStatus}', [FollowUpStatusController::class, 'show'])->name('show');
        Route::put('/{followUpStatus}', [FollowUpStatusController::class, 'update'])->name('update');
        Route::patch('/{followUpStatus}', [FollowUpStatusController::class, 'update'])->name('patch');
        Route::delete('/{followUpStatus}', [FollowUpStatusController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('followup-feedbacks')->name('followup-feedbacks.')->group(function () {
        Route::get('/', [FollowupFeedbackController::class, 'index'])->name('index');
        Route::post('/', [FollowupFeedbackController::class, 'store'])->name('store');
        Route::get('/{followupFeedback}', [FollowupFeedbackController::class, 'show'])->name('show');
        Route::put('/{followupFeedback}', [FollowupFeedbackController::class, 'update'])->name('update');
        Route::patch('/{followupFeedback}', [FollowupFeedbackController::class, 'update'])->name('patch');
        Route::delete('/{followupFeedback}', [FollowupFeedbackController::class, 'destroy'])->name('destroy');
    });

    // Follow-up Feedback Reports
    Route::prefix('followup-reports')->name('followup-reports.')->group(function () {
        Route::get('/first-timers', [FollowupFeedbackController::class, 'getFirstTimersWithFollowups'])->name('first-timers');
        Route::get('/absent-members', [FollowupFeedbackController::class, 'getAbsentMembersWithFollowups'])->name('absent-members');
        Route::get('/all-members', [FollowupFeedbackController::class, 'getMembersWithFollowups'])->name('all-members');
    });

    // Backward compatibility routes for followup feedbacks
    Route::get('/first-timers/followup-feedbacks', [FollowupFeedbackController::class, 'getFirstTimersWithFollowups']);
    Route::get('/absent-members/followup-feedbacks', [FollowupFeedbackController::class, 'getAbsentMembersWithFollowups']);
    Route::get('/all-members/followup-feedbacks', [FollowupFeedbackController::class, 'getMembersWithFollowups']);
    Route::get('/members/{user}/followup-feedbacks', [FollowupFeedbackController::class, 'getFollowUpsByMember']);
    Route::get('/first-timers/{firstTimer}/followup-feedbacks', [FollowupFeedbackController::class, 'getFollowUpsByFirstTimer']);

    // ------------------------------------------------------------------------
    // First Timers Management
    // ------------------------------------------------------------------------
    Route::prefix('first-timers')->name('first-timers.')->group(function () {
        Route::get('/', [FirstTimerController::class, 'index'])->name('index');
        Route::get('/assigned', [FirstTimerController::class, 'getAssignedFirstTimers'])->name('assigned');
        Route::get('/{firstTimer}', [FirstTimerController::class, 'show'])->name('show');
        Route::put('/{firstTimer}', [FirstTimerController::class, 'update'])->name('update');
        Route::post('/{firstTimer}/welcome-email', [FirstTimerController::class, 'sendFirstTimerWelcomeEmail'])->name('welcome-email');
    });

    // ------------------------------------------------------------------------
    // Members Management
    // ------------------------------------------------------------------------
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/', [MemberController::class, 'index'])->name('index');
        Route::post('/', [MemberController::class, 'store'])->name('store');
        Route::get('/users/all', [MemberController::class, 'getAllUsers'])->name('users.all');
        Route::get('/{member}', [MemberController::class, 'show'])->name('show');
        Route::put('/{member}', [MemberController::class, 'update'])->name('update');
        Route::patch('/{member}', [MemberController::class, 'update'])->name('patch');
        Route::delete('/{member}', [MemberController::class, 'destroy'])->name('destroy-single');
        Route::post('/delete', [MemberController::class, 'destroy'])->name('destroy');
    });

    // ------------------------------------------------------------------------
    // Attendance (User)
    // ------------------------------------------------------------------------
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::post('/mark', [AttendanceController::class, 'markAttendance'])->name('mark');
        Route::get('/history', [AttendanceController::class, 'history'])->name('history');
        Route::get('/monthly-stats', [AttendanceController::class, 'getUserAttendanceMonthlyStats'])->name('monthly-stats');
        Route::get('/report', [AttendanceController::class, 'getAttendanceReport'])->name('report');
    });

    // ------------------------------------------------------------------------
    // Usher Attendance
    // ------------------------------------------------------------------------
    Route::prefix('usher-attendance')->name('usher-attendance.')->group(function () {
        Route::get('/', [UsherAttendanceController::class, 'index'])->name('index');
        Route::post('/', [UsherAttendanceController::class, 'store'])->name('store');
        Route::get('/{usherAttendance}', [UsherAttendanceController::class, 'show'])->name('show');
        Route::put('/{usherAttendance}', [UsherAttendanceController::class, 'update'])->name('update');
        Route::patch('/{usherAttendance}', [UsherAttendanceController::class, 'update'])->name('patch');
        Route::delete('/{usherAttendance}', [UsherAttendanceController::class, 'destroy'])->name('destroy');
    });


    // ------------------------------------------------------------------------
    // Messaging System
    // ------------------------------------------------------------------------
    Route::prefix('messages')->name('messages.')->group(function () {
        // Get messages
        Route::get('/inbox', [MessageController::class, 'inbox'])->name('inbox');
        Route::get('/sent', [MessageController::class, 'sent'])->name('sent');
        Route::get('/archived', [MessageController::class, 'archived'])->name('archived');
        Route::get('/unread-count', [MessageController::class, 'unreadCount'])->name('unread-count');

        // Conversations
        Route::get('/conversations', [MessageController::class, 'recentConversations'])->name('conversations');
        Route::get('/conversation/{userId}', [MessageController::class, 'conversation'])->name('conversation');

        // CRUD operations
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::get('/{messageId}', [MessageController::class, 'show'])->name('show');
        Route::delete('/{message}', [MessageController::class, 'destroy'])->name('destroy');

        // Reply to message
        Route::post('/{message}/reply', [MessageController::class, 'reply'])->name('reply');

        // Mark as read/unread
        Route::patch('/{message}/mark-read', [MessageController::class, 'markAsRead'])->name('mark-read');
        Route::patch('/{message}/mark-unread', [MessageController::class, 'markAsUnread'])->name('mark-unread');
        Route::post('/mark-multiple-read', [MessageController::class, 'markMultipleAsRead'])->name('mark-multiple-read');

        // Archive/Unarchive
        Route::patch('/{message}/archive', [MessageController::class, 'archive'])->name('archive');
        Route::patch('/{message}/unarchive', [MessageController::class, 'unarchive'])->name('unarchive');

        // Bulk operations
        Route::post('/bulk-delete', [MessageController::class, 'bulkDelete'])->name('bulk-delete');

        // Search
        Route::get('/search/query', [MessageController::class, 'search'])->name('search');
    });

    // ========================================================================
    // LEADER ROUTES (Admin & Leader Access)
    // ========================================================================
    Route::prefix('leaders')
        ->name('leaders.')
        ->middleware(['role:' . RoleEnum::ADMIN->value . '|' . RoleEnum::LEADER->value])
        ->group(function () {
            // Units Management
            Route::prefix('units')->name('units.')->group(function () {
                Route::get('/', [UnitController::class, 'index'])->name('index');
                Route::post('/', [UnitController::class, 'store'])->name('store');
                Route::get('/{unit}', [UnitController::class, 'show'])->name('show');
                Route::put('/{unit}', [UnitController::class, 'update'])->name('update');
                Route::patch('/{unit}', [UnitController::class, 'update'])->name('patch');
                Route::delete('/{unit}', [UnitController::class, 'destroy'])->name('destroy');
            });

            // Leader-specific User Routes
            Route::get('/absentees', [UserController::class, 'getAssignedAbsentees'])->name('absentees');
            Route::get('/assigned-members', [UserController::class, 'getAssignedMembers'])->name('assigned-members');
        });

    // Backward compatibility for leader routes
    Route::get('/leaders/absentees', [UserController::class, 'getAssignedAbsentees']);
    Route::get('/members/assigned', [UserController::class, 'getAssignedMembers']);

    // ========================================================================
    // ADMIN ROUTES (Admin-Only Access)
    // ========================================================================
    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['role:' . RoleEnum::ADMIN->value])
        ->group(function () {

            Route::prefix('event-registrations')->group(function () {
                Route::put('/{registration}', [RegistrationController::class, 'update']);
                Route::delete('/{registration}', [RegistrationController::class, 'destroy']);
            });

            // --------------------------------------------------------------------
            // Admin Dashboard & Analytics
            // --------------------------------------------------------------------
            Route::get('/analytics', [AdminController::class, 'getAdminAnalytics'])->name('analytics');

            // --------------------------------------------------------------------
            // Admin User Management
            // --------------------------------------------------------------------
            Route::prefix('users')->name('users.')->group(function () {
                Route::post('/assign-role', [AdminController::class, 'assignRoleToUsers'])->name('assign-role');
                Route::post('/sync-permissions', [AdminController::class, 'syncUsersPermissions'])->name('sync-permissions');
            });

            // Backward compatibility
            Route::post('/assign-role', [AdminController::class, 'assignRoleToUsers']);
            Route::post('/sync-permissions', [AdminController::class, 'syncUsersPermissions']);

            // --------------------------------------------------------------------
            // Admin First Timers
            // --------------------------------------------------------------------
            Route::prefix('first-timers')->name('first-timers.')->group(function () {
                Route::get('/report', [FirstTimerController::class, 'getFirstTimerReport'])->name('report');
                Route::get('/analytics', [FirstTimerController::class, 'getFirstTimersAnalytics'])->name('analytics');
                Route::post('/integrated/assign-member-role', [FirstTimerController::class, 'assignMemberRole'])->name('assign-member-role');
            });

            // --------------------------------------------------------------------
            // Admin Members
            // --------------------------------------------------------------------
            Route::prefix('members')->name('members.')->group(function () {
                Route::get('/role/{role}', [MemberController::class, 'getMembersByRole'])->name('by-role');
                Route::post('/assign', [MemberController::class, 'assignMembers'])->name('assign');
                Route::post('/glory-team/update', [MemberController::class, 'updateGloryTeamMembers'])->name('glory-team.update');
            });

            // --------------------------------------------------------------------
            // Admin Forms
            // --------------------------------------------------------------------
            Route::prefix('forms')->name('forms.')->group(function () {
                Route::get('/', [FormController::class, 'index'])->name('index');
                Route::delete('/', [FormController::class, 'destroy'])->name('destroy');
                Route::patch('/completed', [FormController::class, 'markAsCompleted'])->name('mark-completed');
            });

            // --------------------------------------------------------------------
            // Admin Attendance
            // --------------------------------------------------------------------
            Route::prefix('attendance')->name('attendance.')->group(function () {
                Route::get('/', [AttendanceController::class, 'index'])->name('index');
                Route::post('/mark', [AttendanceController::class, 'adminMarkAttendance'])->name('mark');
                Route::post('/mark-absentees', [AttendanceController::class, 'markAbsentees'])->name('mark-absentees');
                Route::post('/assign-absentees-to-leaders', [AttendanceController::class, 'assignAbsenteesToLeaders'])->name('assign-absentees');
                Route::post('/badges/award', [AttendanceController::class, 'awardMonthlyBadges'])->name('badges.award');
                Route::get('/monthly-stats', [AttendanceController::class, 'getAdminAttendanceMonthlyStats'])->name('monthly-stats');
            });

            // --------------------------------------------------------------------
            // Admin Media
            // --------------------------------------------------------------------
            Route::prefix('media')->name('media.')->group(function () {
                Route::post('/fetch', [MediaController::class, 'fetchFromYouTube'])->name('fetch-youtube');
            });

            Route::prefix('events')->group(function () {
                Route::post('/', [EventController::class, 'store']);          // Create event
                Route::put('/{id}', [EventController::class, 'update']);      // Update event
                Route::patch('/{id}', [EventController::class, 'update']);    // Update event (PATCH)
                Route::delete('/{id}', [EventController::class, 'destroy']);  // Delete event
            });

            // --------------------------------------------------------------------
            // Admin Mail
            // --------------------------------------------------------------------
            Route::prefix('mail')->name('mail.')->group(function () {
                Route::post('/bulk', [MailController::class, 'sendBulkMail'])->name('bulk');
            });
        });
});
