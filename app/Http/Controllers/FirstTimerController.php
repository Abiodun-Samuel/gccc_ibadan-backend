<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\FirstTimerResource;
use App\Models\FollowUpStatus;
use App\Models\User;
use App\Services\FirstTimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class FirstTimerController extends Controller
{
    public function __construct(
        private readonly FirstTimerService $firstTimerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'week_ending',
            'date_of_visit',
            'date_month_of_visit',
            'assigned_to_member',
            'follow_up_status'
        ]);

        $firstTimers = $this->firstTimerService->getAllFirstTimers($filters);

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

            if (!empty($validated['secondary_avatar'])) {
                try {
                    $validated['secondary_avatar'] = $this->firstTimerService->handleAvatarUpload(
                        $firstTimer,
                        $validated['secondary_avatar'],
                        $request->folder ?? 'first-timers'
                    );
                } catch (\Exception $e) {
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
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
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

    public function assignMemberRole()
    {
        try {
            DB::beginTransaction();

            $integratedFirstTimers = User::firstTimers()->where('follow_up_status_id', FollowUpStatus::INTEGRATED_ID)
                ->get();

            if ($integratedFirstTimers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No first timers found with integrated status'
                ], 404);
            }

            $assignedCount = 0;
            $alreadyMemberCount = 0;
            $noUserCount = 0;

            foreach ($integratedFirstTimers as $user) {

                if ($user->hasRole(RoleEnum::MEMBER->value)) {
                    $alreadyMemberCount++;
                    continue;
                }
                $user->assignRole(RoleEnum::MEMBER->value);
                $assignedCount++;
            }


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Member role successfully assigned to {$assignedCount} integrated first timers",
                'data' => [
                    'stats' => [
                        'total_found' => $integratedFirstTimers->count(),
                        'assigned' => $assignedCount,
                        'already_member' => $alreadyMemberCount,
                        'no_user_account' => $noUserCount
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while assigning member role',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
