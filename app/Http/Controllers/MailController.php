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
    private const BATCH_SIZE = 50;

    public function __construct(MailService $mailService, UserService $userService)
    {
        $this->mailService = $mailService;
        $this->userService = $userService;
    }

    public function sendBulkMail(SendBulkMailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $users = $this->userService->getUsersForBulkEmail($validated['user_ids']);

            if ($users->isEmpty()) {
                return $this->errorResponse(
                    'No valid users found with email addresses.',
                    Response::HTTP_NOT_FOUND
                );
            }

            // Map users to the recipient shape ZeptoMail expects.
            $recipients = $users->map(fn($user) => [
                'email' => $user->email,
                'name'  => trim("{$user->first_name} {$user->last_name}"),
            ])->values()->all();

            $totalUsers    = count($recipients);
            $batches       = array_chunk($recipients, self::BATCH_SIZE);
            $batchCount    = count($batches);
            $successCount  = 0;
            $failureCount  = 0;
            $failedBatches = [];

            foreach ($batches as $batchIndex => $batch) {
                try {
                    $this->mailService->sendTemplateBatch(
                        templateId: $validated['template_id'],
                        recipients: $batch,
                        ccRecipients: $validated['cc_recipients']  ?? [],
                        bccRecipients: $validated['bcc_recipients'] ?? [],
                    );

                    $successCount += count($batch);

                    Log::info('Bulk email batch sent', [
                        'template_id' => $validated['template_id'],
                        'batch'       => ($batchIndex + 1) . "/{$batchCount}",
                        'recipients'  => count($batch),
                    ]);
                } catch (\Exception $e) {
                    $failureCount += count($batch);

                    $failedBatches[] = [
                        'batch'      => $batchIndex + 1,
                        'recipients' => count($batch),
                        'emails'     => array_column($batch, 'email'),
                        'error'      => $e->getMessage(),
                    ];

                    Log::error('Bulk email batch failed', [
                        'template_id' => $validated['template_id'],
                        'batch'       => ($batchIndex + 1) . "/{$batchCount}",
                        'recipients'  => count($batch),
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $isFullSuccess = $failureCount === 0;

            Log::info('Bulk email sending completed', [
                'template_id'   => $validated['template_id'],
                'total_users'   => $totalUsers,
                'batches_sent'  => $batchCount,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

            return response()->json([
                'success' => $isFullSuccess,
                'message' => $this->composeBulkEmailMessage($totalUsers, $successCount, $failureCount),
                'data'    => [
                    'template_id'   => $validated['template_id'],
                    'total_users'   => $totalUsers,
                    'batches'       => $batchCount,
                    'emails_sent'   => $successCount,
                    'emails_failed' => $failureCount,
                    'failures'      => $failedBatches,
                ],
            ], $isFullSuccess ? Response::HTTP_OK : 207);
        } catch (\Exception $e) {
            Log::error('Bulk email sending failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
}
