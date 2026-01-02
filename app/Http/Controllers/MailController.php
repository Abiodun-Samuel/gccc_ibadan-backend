<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendBulkMailRequest;
use App\Models\PicnicRegistration;
use App\Services\MailService;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MailController extends Controller
{
    protected $mailService;
    protected $userService;

    public function __construct(MailService $mailService, UserService $userService)
    {
        $this->mailService = $mailService;
        $this->userService = $userService;
    }

    public function sendBulkMail(SendBulkMailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Get users with their email and names
            $users = $this->userService->getUsersForBulkEmail($validated['user_ids']);

            if ($users->isEmpty()) {
                return $this->errorResponse(
                    'No valid users found with email addresses.',
                    Response::HTTP_NOT_FOUND
                );
            }

            // Send emails individually
            $successCount = 0;
            $failureCount = 0;
            $failures = [];

            foreach ($users as $user) {
                try {
                    $recipient = [
                        'email' => $user->email,
                        'name' => $user->first_name,
                    ];

                    // Add merge info if requested
                    if ($validated['use_merge_info'] ?? false) {
                        $recipient['merge_info'] = [
                            'name' => $user->first_name,
                        ];
                    }

                    // Send individual email
                    $this->mailService->sendBulkEmail(
                        templateId: $validated['template_id'],
                        recipients: [$recipient],
                        ccRecipients: $validated['cc_recipients'] ?? [],
                        bccRecipients: $validated['bcc_recipients'] ?? [],
                        useMergeInfo: $validated['use_merge_info'] ?? false
                    );

                    $successCount++;

                    // Small delay to avoid rate limiting
                    usleep(100000); // 0.1 seconds

                } catch (\Exception $e) {
                    $failureCount++;
                    $failures[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'error' => $e->getMessage()
                    ];

                    Log::error('Failed to send bulk email to user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $totalUsers = $users->count();
            $isFullSuccess = $failureCount === 0;

            Log::info('Bulk email sending completed', [
                'template_id' => $validated['template_id'],
                'total_users' => $totalUsers,
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ]);

            // Compose appropriate message based on results
            $message = $this->composeBulkEmailMessage($totalUsers, $successCount, $failureCount);

            return response()->json([
                'success' => $isFullSuccess,
                'message' => $message,
                'data' => [
                    'template_id' => $validated['template_id'],
                    'use_merge_info' => $validated['use_merge_info'] ?? false,
                    'total_users' => $totalUsers,
                    'emails_sent' => $successCount,
                    'emails_failed' => $failureCount,
                    'failures' => $failures
                ]
            ], $isFullSuccess ? Response::HTTP_OK : 207);
        } catch (\Exception $e) {
            Log::error('Bulk email sending failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to send bulk email: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Compose an appropriate message for bulk email results
     *
     * @param int $totalUsers
     * @param int $successCount
     * @param int $failureCount
     * @return string
     */
    private function composeBulkEmailMessage(
        int $totalUsers,
        int $successCount,
        int $failureCount
    ): string {
        // All emails sent successfully
        if ($failureCount === 0) {
            return "Successfully sent emails to all {$successCount} recipients.";
        }

        // All emails failed
        if ($successCount === 0) {
            return "Failed to send emails to all {$totalUsers} recipients. Please check the email addresses and try again.";
        }

        // Partial success
        $successRate = round(($successCount / $totalUsers) * 100, 1);
        return "Sent {$successCount} out of {$totalUsers} emails ({$successRate}% success rate). {$failureCount} email(s) failed to send.";
    }
    public function sendVenueEmail(): JsonResponse
    {
        try {
            $year = now()->year;

            $registrations = PicnicRegistration::with('user:id,first_name,last_name,email')
                ->forYear($year)
                ->get();

            if ($registrations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No registrations found for year {$year}"
                ], 404);
            }

            // Send to all participants
            $successCount = 0;
            $failureCount = 0;
            $failures = [];

            foreach ($registrations as $registration) {
                try {
                    $this->mailService->sendPicnicVenueEmail([
                        [
                            'email' => $registration->user->email,
                            'name' => $registration->user->first_name
                        ]
                    ]);

                    $successCount++;

                    usleep(100000);
                } catch (\Exception $e) {
                    $failureCount++;
                    $failures[] = [
                        'user_id' => $registration->user_id,
                        'email' => $registration->user->email,
                        'name' => $registration->user->first_name . ' ' . $registration->user->last_name,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => $failureCount === 0,
                'message' => $failureCount === 0
                    ? 'All venue emails sent successfully'
                    : 'Some emails failed to send',
                'statistics' => [
                    'total_registrations' => $registrations->count(),
                    'emails_sent' => $successCount,
                    'emails_failed' => $failureCount,
                    'success_rate' => round(($successCount / $registrations->count()) * 100, 2) . '%'
                ],
                'failures' => $failures
            ], $failureCount === 0 ? 200 : 207);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send venue emails: ' . $e->getMessage()
            ], 500);
        }
    }
}
