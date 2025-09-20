<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFirstTimerRequest;
use App\Http\Requests\UpdateFirstTimerRequest;
use App\Http\Resources\FirstTimerResource;
use App\Models\FirstTimer;
use App\Models\FirstTimerFollowUp;
use App\Models\FollowUpStatus;
use App\Services\FirstTimerFollowupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FirstTimerController extends Controller
{
    public $followupService;
    public function __construct(FirstTimerFollowupService $followupService)
    {
        $this->followupService = $followupService;
    }

    public function index(Request $request)
    {
        $firstTimers = FirstTimer::with(['followUpStatus', 'assignedTo'])->orderBy('date_of_visit', 'desc')->get();
        return $this->successResponse(
            FirstTimerResource::collection($firstTimers),
            'First timers retrieved successfully',
            Response::HTTP_OK
        );
    }
    public function store(StoreFirstTimerRequest $request)
    {
        $data = $request->validated();
        $followupMember = $this->followupService->findLeastLoadedFollowupMember($data['gender']);

        $firstTimer = FirstTimer::create(array_merge($data, [
            'assigned_to_member_id' => optional($followupMember)->id,
            'follow_up_status_id' => FollowUpStatus::DEFAULT_STATUS_ID,
            'assigned_at' => now(),
            'week_ending' => getNextSunday()?->toDateString()
        ]));

        return $this->successResponse($firstTimer, 'First timer created successfully', Response::HTTP_CREATED);
    }

    public function show(FirstTimer $firstTimer)
    {
        return $this->successResponse(
            new FirstTimerResource($firstTimer),
            'First timer retrieved successfully'
        );
    }
    public function update(UpdateFirstTimerRequest $request, FirstTimer $firstTimer)
    {
        $firstTimer->update($request->validated());
        return $this->successResponse(
            new FirstTimerResource($firstTimer->load(['followUpStatus', 'assignedTo', 'followupNotes'])),
            'First timer updated successfully',
            201
        );
    }
    public function destroy(FirstTimer $firstTimer)
    {
        $firstTimer->delete();

        return $this->successResponse(
            null,
            'First timer deleted successfully'
        );
    }
    public function assignFollowup(FirstTimer $firstTimer)
    {
        $candidate = $this->followupService->findLeastLoadedFollowupMember($firstTimer);

        if (!$candidate) {
            return $this->errorResponse(null, 'No eligible follow-up member found or Follow-up unit missing', 422);
        }

        $firstTimer->assigned_to_member_id = $candidate->id;
        $firstTimer->save();

        return $this->successResponse(new FirstTimerResource($firstTimer->load('followUpStatus', 'assignedTo')), 'Assigned follow-up member successfully');
    }
    public function unassignFollowup(FirstTimer $firstTimer)
    {
        $this->followupService->unassign($firstTimer);
        return $this->successResponse(new FirstTimerResource($firstTimer->fresh()->load('followUpStatus', 'assignedTo')), 'Unassigned follow-up member');
    }
    public function setFollowupStatus(Request $request, FirstTimer $firstTimer)
    {
        $payload = $request->validate([
            'status' => ['nullable', 'string'],
            'status_id' => ['nullable', 'integer', 'exists:follow_up_statuses,id'],
        ]);

        if (!empty($payload['status'])) {
            $status = FollowUpStatus::where('title', $payload['status'])->first();
            if (!$status) {
                return $this->errorResponse(null, 'Status not found', 404);
            }
        } else {
            $status = FollowUpStatus::find($payload['status_id']);
        }

        $firstTimer->follow_up_status_id = $status->id;
        $firstTimer->save();

        return $this->successResponse(new FirstTimerResource($firstTimer->fresh()->load('followUpStatus', 'assignedTo')), 'Status updated');
    }
    public function addFollowupNote(Request $request, $id)
    {
        $firstTimer = FirstTimer::findOrFail($id);
        $payload = $request->validate([
            'note' => 'required|string',
        ]);

        $user = $request->user();
        // authorization: admin OR leader of followup unit OR assigned followup member
        $isAdmin = $user->hasRole('admin');
        $isAssigned = $firstTimer->assigned_to_member_id && $firstTimer->assigned_to_member_id == $user->id;

        $isLeader = $user->leadingUnits()->where('units.name', 'Followup')->exists();

        if (!($isAdmin || $isAssigned || $isLeader)) {
            return $this->errorResponse(null, 'Not authorized to add follow-up notes', 403);
        }
        $note = FirstTimerFollowUp::create([
            'first_timer_id' => $firstTimer->id,
            'user_id' => $user->id,
            'note' => $payload['note'],
        ]);
        return $this->successResponse($note, 'Follow-up note added', 201);
    }
    public function listFollowupNotes($id)
    {
        $firstTimer = FirstTimer::findOrFail($id);
        $notes = $firstTimer->followupNotes()->with('user:id,first_name,last_name,email')->orderBy('created_at', 'desc')->get();
        return $this->successResponse($notes, 'Follow-up notes retrieved');
    }
}
