<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MailService;
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
}
