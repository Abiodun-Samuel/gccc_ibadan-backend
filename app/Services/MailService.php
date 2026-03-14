<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailService
{
    protected $apiUrl;
    protected $apiKey;
    private const POOL_SIZE = 10; // concurrent requests per pool chunk

    public function __construct()
    {
        $this->apiUrl = config('services.zeptomail.base_url');
        $this->apiKey = config('services.zeptomail.api_key');
    }

    private function sendEmail(array $data): array
    {
        $response = Http::withHeaders($this->buildRequestHeaders())
            ->timeout(60)
            ->post($this->apiUrl, $data);

        if (!$response->successful()) {
            $this->handleApiError($response);
        }

        return $response->json();
    }

    // private function sendEmail(array $data): array
    // {
    //     $response = Http::withHeaders([
    //         'accept' => 'application/json',
    //         'authorization' => "Zoho-enczapikey {$this->apiKey}",
    //         'cache-control' => 'no-cache',
    //         'content-type' => 'application/json'
    //     ])
    //         ->timeout(60)
    //         ->post($this->apiUrl, $data);

    //     if (!$response->successful()) {
    //         $this->handleApiError($response);
    //     }

    //     return $response->json();
    // }

    private function handleApiError($response): void
    {
        $statusCode   = $response->status();
        $responseBody = $response->json();

        // Log the FULL body so we can see exactly what ZeptoMail rejected
        Log::error('ZeptoMail API error', [
            'status'   => $statusCode,
            'body'     => $responseBody,      // ← this is the key addition
        ]);

        $errorMessage = 'Email service error occurred';

        if (isset($responseBody['error']['details'][0]['message'])) {
            $errorMessage = $responseBody['error']['details'][0]['message'];
        } elseif (isset($responseBody['message'])) {
            $errorMessage = $responseBody['message'];
        } elseif (isset($responseBody['error'])) {
            $errorMessage = is_string($responseBody['error'])
                ? $responseBody['error']
                : json_encode($responseBody['error']);
        }

        $userMessage = match ($statusCode) {
            400 => "Invalid email request: {$errorMessage}",   // ← include actual message for 400s
            401 => 'Email service authentication failed.',
            403 => 'Not authorized to send emails.',
            429 => 'Too many email requests. Please try again later.',
            500, 502, 503, 504 => 'Email service is temporarily unavailable.',
            default => "Failed to send email: {$errorMessage}",
        };

        throw new \Exception($userMessage, $statusCode);
    }
    // private function handleApiError($response): void
    // {
    //     $statusCode = $response->status();
    //     $responseBody = $response->json();

    //     $errorMessage = 'Email service error occurred';

    //     if (isset($responseBody['error']['details'][0]['message'])) {
    //         $errorMessage = $responseBody['error']['details'][0]['message'];
    //     } elseif (isset($responseBody['message'])) {
    //         $errorMessage = $responseBody['message'];
    //     } elseif (isset($responseBody['error'])) {
    //         $errorMessage = is_string($responseBody['error'])
    //             ? $responseBody['error']
    //             : json_encode($responseBody['error']);
    //     }

    //     $userMessage = match ($statusCode) {
    //         400 => 'Invalid email request. Please check the email details.',
    //         401 => 'Email service authentication failed.',
    //         403 => 'Not authorized to send emails.',
    //         429 => 'Too many email requests. Please try again later.',
    //         500, 502, 503, 504 => 'Email service is temporarily unavailable.',
    //         default => "Failed to send email: {$errorMessage}",
    //     };
    //     throw new \Exception($userMessage, $statusCode);
    // }

    private function buildRecipientsArray(array $recipients): array
    {
        return array_map(fn($recipient) => [
            "email_address" => [
                "address" => $recipient['email'],
                "name" => $recipient['name'] ?? ''
            ]
        ], $recipients);
    }

    public function sendNewMessageNotificationEmail(
        array $recipients,
        string $senderName,
        array $ccRecipients = [],
        array $bccRecipients = []
    ): array {
        if (empty($recipients)) {
            throw new \Exception('No recipients provided for message notification email.');
        }

        $firstRecipient = $recipients[0];

        $data = [
            "mail_template_key" => env('MESSAGE_NOTIFICATION_TEMPLATE_ID'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "GCCC Ibadan"
            ],
            "to" => $this->buildRecipientsArray($recipients),
            "merge_info" => [
                "name" => $firstRecipient['name'] ?? '',
                "sender_name" => $senderName,
            ]
        ];

        if (!empty($ccRecipients)) {
            $data['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($data);
    }

    public function sendAbsentMemberAssignmentEmail(
        array $recipients = [],
        array $ccRecipients = [],
        array $bccRecipients = []
    ): array {
        $data = [
            "mail_template_key" => env('assisgnment_email_template_id'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $this->buildRecipientsArray($recipients),
        ];

        if (!empty($ccRecipients)) {
            $data['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($data);
    }

    public function sendAssignedMemberEmail(
        array $recipients = [],
        array $ccRecipients = [],
        array $bccRecipients = []
    ): array {
        $firstRecipient = $recipients[0];

        $data = [
            "mail_template_key" => env('assigned_member_email_template_id'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $this->buildRecipientsArray($recipients),
            "merge_info" => [
                "name" => $firstRecipient['name'] ?? '',
            ]
        ];

        if (!empty($ccRecipients)) {
            $data['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($data);
    }

    public function sendFirstTimerAssignedEmail(
        array $recipients = [],
        array $ccRecipients = [],
        array $bccRecipients = [],
        array $data = []
    ): array {
        $firstRecipient = $recipients[0];
        $first_timer_name = $data['first_timer_name'];
        $first_timer_email = $data['first_timer_email'];
        $first_timer_phone = $data['first_timer_phone'];

        $emailData = [
            "mail_template_key" => env('firstTimerAssignedTemplateId'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $this->buildRecipientsArray($recipients),
            "merge_info" => [
                "name" => $firstRecipient['name'] ?? '',
                "first_timer_name" => $first_timer_name ?? '',
                "first_timer_email" => $first_timer_email ?? '',
                "first_timer_phone" => $first_timer_phone ?? '',
            ]
        ];

        if (!empty($ccRecipients)) {
            $emailData['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $emailData['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($emailData);
    }

    public function sendFirstTimerWelcomeEmail(
        array $recipients,
        array $ccRecipients = [],
        array $bccRecipients = []
    ): array {
        if (empty($recipients)) {
            throw new \Exception('No recipients provided for welcome email.');
        }

        $firstRecipient = $recipients[0];

        $data = [
            "mail_template_key" => env('firsttimer_welcome_email_template_id'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $this->buildRecipientsArray($recipients),
            "merge_info" => [
                "name" => $firstRecipient['name'] ?? '',
            ]
        ];

        if (!empty($ccRecipients)) {
            $data['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($data);
    }

    public function sendResetPasswordEmail($resetUrl, array $recipients): array
    {
        if (empty($recipients)) {
            throw new \Exception('No recipients provided for welcome email.');
        }

        $firstRecipient = $recipients[0];

        $data = [
            "mail_template_key" => env('password_reset_email_template_id'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $this->buildRecipientsArray($recipients),
            "merge_info" => [
                "name" => $firstRecipient['name'] ?? '',
                "email" => $firstRecipient['email'] ?? '',
                "resetUrl" => $resetUrl,
            ]
        ];

        return $this->sendEmail($data);
    }

    public function sendBulkEmail(
        string $templateId,
        array $recipients = [],
        array $ccRecipients = [],
        array $bccRecipients = [],
        bool $useMergeInfo = false
    ): array {
        if (empty($recipients)) {
            throw new \Exception('No recipients provided for bulk email.');
        }

        $data = [
            "mail_template_key" => $templateId,
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => []
        ];

        // Build recipients
        foreach ($recipients as $recipient) {
            $to = [
                "email_address" => [
                    "address" => $recipient['email'],
                    "name" => $recipient['name'] ?? ''
                ]
            ];

            $data['to'][] = $to;
        }

        // Add merge_info at root level if using personalization (for single recipient)
        if ($useMergeInfo && count($recipients) === 1 && !empty($recipients[0]['merge_info'])) {
            $data['merge_info'] = $recipients[0]['merge_info'];
        }

        if (!empty($ccRecipients)) {
            $data['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($data);
    }

    public function sendNewMembersAssignedMail(
        array $recipients = [],
        array $ccRecipients = [],
        array $bccRecipients = []
    ): array {
        if (empty($recipients)) {
            throw new \Exception('No recipients provided for bulk email.');
        }

        $data = [
            "mail_template_key" => env('ASSIGNED_MEMBERS_TEMPLATE_ID'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $this->buildRecipientsArray($recipients),
        ];

        if (!empty($ccRecipients)) {
            $data['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $this->sendEmail($data);
    }

    public function sendTemplateBatch(
        string $templateId,
        array  $recipients,
        array  $ccRecipients  = [],
        array  $bccRecipients = [],
        bool   $useMergeInfo  = false
    ): array {
        if (empty($recipients)) {
            throw new \InvalidArgumentException('Recipients array cannot be empty.');
        }

        if (!$useMergeInfo) {
            $data = [
                'mail_template_key' => $templateId,
                'from' => [
                    'address' => 'admin@gcccibadan.org',
                    'name'    => 'GCCC IBADAN',
                ],
                'to' => $this->buildRecipientsArray($recipients),
            ];

            if (!empty($ccRecipients)) {
                $data['cc'] = $this->buildRecipientsArray($ccRecipients);
            }

            if (!empty($bccRecipients)) {
                $data['bcc'] = $this->buildRecipientsArray($bccRecipients);
            }

            return $this->sendEmail($data);
        }

        return $this->sendPersonalisedBatch(
            templateId: $templateId,
            recipients: $recipients,
            ccRecipients: $ccRecipients,
            bccRecipients: $bccRecipients,
        );
    }

    /**
     * Send one personalised email per recipient using concurrent HTTP requests.
     * Requests are chunked into pools of POOL_SIZE to avoid overwhelming the
     * ZeptoMail API or hitting connection limits.
     */
    private function sendPersonalisedBatch(
        string $templateId,
        array  $recipients,
        array  $ccRecipients  = [],
        array  $bccRecipients = []
    ): array {
        $results    = [];
        $errors     = [];
        $chunks     = array_chunk($recipients, self::POOL_SIZE);
        $headers    = $this->buildRequestHeaders();

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function (Pool $pool) use (
                $chunk,
                $templateId,
                $ccRecipients,
                $bccRecipients,
                $headers
            ) {
                foreach ($chunk as $index => $recipient) {
                    $payload = $this->buildPersonalisedPayload(
                        templateId: $templateId,
                        recipient: $recipient,
                        ccRecipients: $ccRecipients,
                        bccRecipients: $bccRecipients,
                    );

                    $pool->as((string) $index)
                        ->withHeaders($headers)
                        ->timeout(60)
                        ->post($this->apiUrl, $payload);
                }
            });

            foreach ($chunk as $index => $recipient) {
                $response = $responses[(string) $index];

                // Pool responses are either a Response or a ConnectionException
                if ($response instanceof \Illuminate\Http\Client\ConnectionException) {
                    $errors[] = [
                        'email' => $recipient['email'],
                        'error' => 'Connection failed: ' . $response->getMessage(),
                    ];
                    continue;
                }

                if (!$response->successful()) {
                    $errors[] = [
                        'email'  => $recipient['email'],
                        'status' => $response->status(),
                        'error'  => $this->extractApiErrorMessage($response),
                    ];
                    continue;
                }

                $results[] = $response->json();
            }
        }

        if (!empty($errors)) {
            Log::error('Personalised batch: some recipients failed', [
                'template_id'    => $templateId,
                'total'          => count($recipients),
                'failed'         => count($errors),
                'failed_details' => $errors,
            ]);

            // Partial failures are reported but don't abort the whole batch.
            // The controller's own failure tracking will catch this via the
            // success/fail counts — only throw if EVERY recipient failed.
            if (count($errors) === count($recipients)) {
                throw new \Exception(
                    'All personalised emails failed: ' . $errors[0]['error']
                );
            }
        }

        return $results;
    }

    private function buildPersonalisedPayload(
        string $templateId,
        array  $recipient,
        array  $ccRecipients  = [],
        array  $bccRecipients = []
    ): array {
        $payload = [
            'mail_template_key' => $templateId,
            'from' => [
                'address' => 'admin@gcccibadan.org',
                'name'    => 'GCCC IBADAN',
            ],
            'to' => [
                [
                    'email_address' => [
                        'address' => $recipient['email'],
                        'name'    => $recipient['name'] ?? '',
                    ],
                ],
            ],
            'merge_info' => [
                'name' => $recipient['name'] ?? '',
            ],
        ];

        if (!empty($ccRecipients)) {
            $payload['cc'] = $this->buildRecipientsArray($ccRecipients);
        }

        if (!empty($bccRecipients)) {
            $payload['bcc'] = $this->buildRecipientsArray($bccRecipients);
        }

        return $payload;
    }

    /**
     * Extracted so both sendEmail() and the pool path share the same headers.
     */
    private function buildRequestHeaders(): array
    {
        return [
            'accept'        => 'application/json',
            'authorization' => "Zoho-enczapikey {$this->apiKey}",
            'cache-control' => 'no-cache',
            'content-type'  => 'application/json',
        ];
    }

    /**
     * Extracted from handleApiError() so pool responses can use it too
     * without throwing — the pool loop decides whether to throw.
     */
    private function extractApiErrorMessage($response): string
    {
        $body = $response->json();

        if (isset($body['error']['details'][0]['message'])) {
            return $body['error']['details'][0]['message'];
        }

        if (isset($body['message'])) {
            return $body['message'];
        }

        if (isset($body['error'])) {
            return is_string($body['error']) ? $body['error'] : json_encode($body['error']);
        }

        return 'Unknown error';
    }
}
