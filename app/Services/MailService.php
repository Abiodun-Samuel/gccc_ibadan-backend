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

    private function sendEmail(array $data)
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
            $data = json_decode($response->json(), true);
            $message = $data->error->details[0]->message;
            throw new \Exception("ZeptoMail API Error: $message");
        }

        return $response->json();
    }

    public function sendAbsentMemberAssignmentEmail($recipients = [], $ccRecipients = [], $bccRecipients = [])
    {
        $toArray = [];
        foreach ($recipients as $recipient) {
            $toArray[] = [
                "email_address" => [
                    "address" => $recipient['email'],
                    "name" => $recipient['name'] ?? ''
                ]
            ];
        }

        $ccArray = [];
        foreach ($ccRecipients as $cc) {
            $ccArray[] = [
                "email_address" => [
                    "address" => $cc['email'],
                    "name" => $cc['name'] ?? ''
                ]
            ];
        }

        $bccArray = [];
        foreach ($bccRecipients as $bcc) {
            $bccArray[] = [
                "email_address" => [
                    "address" => $bcc['email'],
                    "name" => $bcc['name'] ?? ''
                ]
            ];
        }

        $data = [
            "mail_template_key" => env('assisgnment_email_template_id'),
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Admin from GCCC IBADAN"
            ],
            "to" => $toArray,
        ];

        if (!empty($ccArray)) {
            $data['cc'] = $ccArray;
        }

        if (!empty($bccArray)) {
            $data['bcc'] = $bccArray;
        }

        try {
            $result = $this->sendEmail($data);

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
// USAGE EXAMPLES
// ============================================

    // Example 1: Multiple TO recipients
    public function exampleMultipleTo()
    {
        $recipients = [
            ['email' => 'user1@example.com', 'name' => 'User One'],
            ['email' => 'user2@example.com', 'name' => 'User Two'],
            ['email' => 'user3@example.com', 'name' => 'User Three']
        ];

        return $this->sendAbsentMemberAssignmentEmail($recipients);
    }

    // Example 2: TO recipients with CC
    public function exampleWithCC()
    {
        $recipients = [
            ['email' => 'primary@example.com', 'name' => 'Primary User']
        ];

        $ccRecipients = [
            ['email' => 'admin@gcccibadan.org', 'name' => 'Admin'],
            ['email' => 'manager@gcccibadan.org', 'name' => 'Manager']
        ];

        return $this->sendAbsentMemberAssignmentEmail($recipients, $ccRecipients);
    }

    // Example 3: TO, CC, and BCC recipients
    public function exampleWithAll()
    {
        $recipients = [
            ['email' => 'member1@example.com', 'name' => 'Member One'],
            ['email' => 'member2@example.com', 'name' => 'Member Two']
        ];

        $ccRecipients = [
            ['email' => 'supervisor@gcccibadan.org', 'name' => 'Supervisor']
        ];

        $bccRecipients = [
            ['email' => 'archive@gcccibadan.org', 'name' => 'Archive']
        ];

        return $this->sendAbsentMemberAssignmentEmail($recipients, $ccRecipients, $bccRecipients);
    }

    // ============================================
// SIMPLE HARDCODED VERSION (Quick Fix)
// ============================================

    public function sendAbsentMemberAssignmentEmailSimple()
    {
        $data = [
            "mail_template_key" => "",
            "from" => [
                "address" => "admin@gcccibadan.org",
                "name" => "Daphne from GCCC IBADAN"
            ],
            // Multiple TO recipients
            "to" => [
                [
                    "email_address" => [
                        "address" => "abiodunsamyemi@gmail.com",
                        "name" => "Daphne"
                    ]
                ],
                [
                    "email_address" => [
                        "address" => "user2@example.com",
                        "name" => "User Two"
                    ]
                ],
                [
                    "email_address" => [
                        "address" => "user3@example.com",
                        "name" => "User Three"
                    ]
                ]
            ],
            // CC recipients (optional)
            "cc" => [
                [
                    "email_address" => [
                        "address" => "manager@gcccibadan.org",
                        "name" => "Manager"
                    ]
                ]
            ],
            // BCC recipients (optional)
            "bcc" => [
                [
                    "email_address" => [
                        "address" => "archive@gcccibadan.org",
                        "name" => "Archive"
                    ]
                ]
            ],
            "merge_info" => [
                "name" => "Daphne",
            ]
        ];

        try {
            $result = $this->sendEmail($data);

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
