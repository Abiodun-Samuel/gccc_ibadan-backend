<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MailService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.zeptomail.base_url');
        $this->apiKey = config('services.zeptomail.api_key');
    }

    private function sendEmail(array $data): array
    {
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'authorization' => "Zoho-enczapikey {$this->apiKey}",
            'cache-control' => 'no-cache',
            'content-type' => 'application/json'
        ])
            ->timeout(60)
            ->post($this->apiUrl, $data);

        if (!$response->successful()) {
            $this->handleApiError($response);
        }

        return $response->json();
    }

    private function handleApiError($response): void
    {
        $statusCode = $response->status();
        $responseBody = $response->json();

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
            400 => 'Invalid email request. Please check the email details.',
            401 => 'Email service authentication failed.',
            403 => 'Not authorized to send emails.',
            429 => 'Too many email requests. Please try again later.',
            500, 502, 503, 504 => 'Email service is temporarily unavailable.',
            default => "Failed to send email: {$errorMessage}",
        };
        throw new \Exception($userMessage, $statusCode);
    }

    private function buildRecipientsArray(array $recipients): array
    {
        return array_map(fn($recipient) => [
            "email_address" => [
                "address" => $recipient['email'],
                "name" => $recipient['name'] ?? ''
            ]
        ], $recipients);
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
            // "mail_template_key" => config('services.zeptomail.templates.first_timer_welcome'),
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
}
