<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFollowupFeedbackRequest;
use App\Http\Resources\AbsenteeResource;
use App\Http\Resources\FirstTimerResource;
use App\Http\Resources\FollowupFeedbackResource;
use App\Http\Resources\UserResource;
use App\Models\AbsenteeAssignment;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FollowupFeedbackController extends Controller
{
    public $followUpService;
    public function __construct(FollowUpService $followUpService)
    {
        $this->followUpService = $followUpService;
    }
    public function store(StoreFollowupFeedbackRequest $request): JsonResponse
    {
        $followUp = $this->followUpService->createFollowUp($request->validated());
        return $this->successResponse(
            new FollowupFeedbackResource($followUp),
            'Followup feedback has been saved successfully',
            Response::HTTP_OK
        );
    }
    public function getFollowUpsByFirstTimer(User $firstTimer): JsonResponse
    {
        $followUps = $this->followUpService->getFollowUps($firstTimer);
        return $this->successResponse(
            FollowupFeedbackResource::collection($followUps),
            '',
            Response::HTTP_OK
        );
    }
    public function getFollowUpsByMember(User $user): JsonResponse
    {
        $followUps = $this->followUpService->getFollowUps($user);
        return $this->successResponse(
            FollowupFeedbackResource::collection($followUps),
            '',
            Response::HTTP_OK
        );
    }
    public function getFirstTimersWithFollowups()
    {
        $firstTimers = User::firstTimers()->with([
            'followUpStatus',
            'assignedTo',
            'followupFeedbacks.createdBy' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'avatar');
            }
        ])->where('status', 'active')->orderBy('date_of_visit', 'desc')->get();
        return $this->successResponse(FirstTimerResource::collection($firstTimers), 'First timers retrieved successfully', Response::HTTP_OK);
    }
    public function getAbsentMembersWithFollowups()
    {
        $assignments = AbsenteeAssignment::with(['user.followupFeedbacks.createdBy', 'attendance.service', 'leader'])
            ->orderByDesc('updated_at')->get();
        return $this->successResponse(AbsenteeResource::collection($assignments), 'Absent members retrieved successfully', Response::HTTP_OK);
    }
    public function getMembersWithFollowups()
    {
        $members = User::members()->with(['followupFeedbacks.createdBy', 'assignedTo'])
            ->orderByDesc('assigned_at')->get();
        return $this->successResponse(UserResource::collection($members), 'All members retrieved successfully', Response::HTTP_OK);
    }
}
