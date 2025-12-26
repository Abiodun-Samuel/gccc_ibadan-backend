<?php

namespace App\Http\Controllers;

use App\Models\PicnicRegistration;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Http\Client\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MailController extends Controller
{
    protected $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function sendBulkMail(): JsonResponse
    {
        try {
            $users = User::whereNotNull('email')
                ->where('email', '!=', '')
                ->select('email', 'first_name')
                ->get()
                ->map(function ($user) {
                    return [
                        'email' => $user->email,
                        'name' => $user->first_name ?? ''
                    ];
                })
                ->toArray();

            if (empty($users)) {
                return $this->errorResponse(
                    'No users found to send email to.',
                    Response::HTTP_NOT_FOUND
                );
            }

            $response = $this->mailService->sendBulkEmail(recipients: $users);

            return $this->successResponse(
                [
                    'recipients_count' => count($users),
                    'response' => $response
                ],
                'Bulk email sent successfully to all users',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
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
