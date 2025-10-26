<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\FirstTimerResource;
use App\Models\User;
use App\Services\FirstTimerService;
use App\Services\MailService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class FirstTimerController extends Controller
{
    public function __construct(
        private readonly FirstTimerService $firstTimerService,
        private readonly MailService $mailService,
        private readonly UploadService $uploadService
    ) {
    }

    public function index(): JsonResponse
    {
        $firstTimers = $this->firstTimerService->getAllFirstTimers();

        return $this->successResponse(
            FirstTimerResource::collection($firstTimers),
            'First timers retrieved successfully',
            Response::HTTP_OK
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $firstTimer = $this->firstTimerService->createFirstTimer($request->validated());

        return $this->successResponse(
            new FirstTimerResource($firstTimer),
            'First timer created successfully',
            Response::HTTP_CREATED
        );
    }

    public function show(User $firstTimer): JsonResponse
    {
        try {
            $firstTimer = $this->firstTimerService->getFirstTimerById($firstTimer);
            return $this->successResponse(
                new FirstTimerResource($firstTimer),
                'First timer retrieved successfully',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_NOT_FOUND
            );
        }
    }

    public function update(UpdateUserRequest $request, User $firstTimer): JsonResponse
    {
        try {
            $validated = $request->validated();

            if (!empty($validated['avatar'])) {
                try {
                    $validated['avatar'] = $this->firstTimerService->handleAvatarUpload(
                        $firstTimer,
                        $validated['avatar'],
                        $request->folder ?? 'first-timers'
                    );
                } catch (InvalidArgumentException $e) {
                    return $this->errorResponse(
                        $e->getMessage(),
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            }

            $updatedFirstTimer = $this->firstTimerService->updateFirstTimer($firstTimer, $validated);

            return $this->successResponse(
                new FirstTimerResource($updatedFirstTimer),
                'First timer updated successfully',
                Response::HTTP_OK
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'An error occurred while updating the record. Please try again.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getAssignedFirstTimers(Request $request): JsonResponse
    {
        $firstTimers = $this->firstTimerService->getAssignedFirstTimers($request->user());

        return $this->successResponse(
            FirstTimerResource::collection($firstTimers),
            'First timers retrieved successfully',
            Response::HTTP_OK
        );
    }

    public function getFirstTimersAnalytics(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        $analytics = $this->firstTimerService->getFirstTimersAnalytics($year);

        return $this->successResponse(
            $analytics,
            'First timers analytics retrieved successfully',
            Response::HTTP_OK
        );
    }

    public function sendFirstTimerWelcomeEmail(User $firstTimer, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255'
        ]);

        try {
            $updatedFirstTimer = $this->firstTimerService->sendWelcomeEmail(
                $firstTimer,
                $validated['email'],
                $validated['name']
            );

            return $this->successResponse(
                new FirstTimerResource($updatedFirstTimer),
                'Welcome email sent successfully',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
