<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendBulkMailRequest;
use App\Services\MailService;
use App\Services\UserService;
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

            $recipients = $this->resolveRecipients($validated);

            if (empty($recipients)) {
                return $this->errorResponse(
                    'No valid recipients found with email addresses.',
                    Response::HTTP_NOT_FOUND
                );
            }

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
                        useMergeInfo: (bool) ($validated['use_merge_info'] ?? false),
                    );

                    $successCount += count($batch);
                } catch (\Exception $e) {
                    $failureCount += count($batch);

                    $failedBatches[] = [
                        'batch'      => $batchIndex + 1,
                        'recipients' => count($batch),
                        'emails'     => array_column($batch, 'email'),
                        'error'      => $e->getMessage(),
                    ];
                }
            }

            $isFullSuccess = $failureCount === 0;

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

            return $this->errorResponse(
                'Failed to send bulk email: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    /**
     * Resolve the final list of recipients for a bulk send.
     *
     * Recipients can come from two sources, which may be used independently
     * or together:
     *   - user_ids: resolved to users (and their stored names) via UserService.
     *   - emails:   a plain list of email-address strings.
     *
     * Results are merged and de-duplicated by (lower-cased) email, with
     * user-derived recipients taking precedence so their names are kept.
     *
     * @param array $validated
     * @return array<int, array{email: string, name: string}>
     */
    private function resolveRecipients(array $validated): array
    {
        $recipients = [];

        // Existing behaviour: build recipients from selected user IDs.
        if (!empty($validated['user_ids'])) {
            $users = $this->userService->getUsersForBulkEmail($validated['user_ids']);

            foreach ($users as $user) {
                $email = strtolower(trim($user->email));

                if ($email === '') {
                    continue;
                }

                $recipients[$email] = [
                    'email' => $user->email,
                    'name'  => trim("{$user->first_name} {$user->last_name}"),
                ];
            }
        }

        // New alternative: build recipients from a raw list of email strings.
        if (!empty($validated['emails'])) {
            foreach ($validated['emails'] as $email) {
                $normalized = strtolower(trim($email));

                // Don't overwrite a user-derived recipient (keeps its name).
                if ($normalized === '' || isset($recipients[$normalized])) {
                    continue;
                }

                $recipients[$normalized] = [
                    'email' => trim($email),
                    'name'  => '',
                ];
            }
        }

        return array_values($recipients);
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
