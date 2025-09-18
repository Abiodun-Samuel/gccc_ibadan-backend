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
use Illuminate\Support\Facades\DB;
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
        $firstTimers = FirstTimer::with(['followUpStatus', 'assignedTo'])->orderBy('date_of_visit', 'desc')->paginate(20);

        return $this->paginatedResponse(
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
    public function analytics(Request $request)
    {
        $year = $request->query('year', now()->year);

        // Handle string date formats: 20-Apr-2025 OR 8/31/2025
        $dateExpression = "
        COALESCE(
            STR_TO_DATE(date_of_visit, '%d-%b-%Y'),
            STR_TO_DATE(date_of_visit, '%m/%d/%Y')
        )
    ";

        // 1. Total first timers per month
        $firstTimersPerMonth = FirstTimer::query()
            ->selectRaw("MONTH($dateExpression) as month, COUNT(*) as total")
            ->whereRaw("YEAR($dateExpression) = ?", [$year])
            ->groupByRaw("MONTH($dateExpression)")
            ->orderByRaw("MONTH($dateExpression)")
            ->pluck('total', 'month'); // key = month, value = count

        // 2. Integrated first timers (follow_up_status = Completed)
        $integratedPerMonth = FirstTimer::query()
            ->selectRaw("MONTH($dateExpression) as month, COUNT(*) as total")
            ->where('follow_up_status', 'Integrated')
            ->whereRaw("YEAR($dateExpression) = ?", [$year])
            ->groupByRaw("MONTH($dateExpression)")
            ->orderByRaw("MONTH($dateExpression)")
            ->pluck('total', 'month');

        // Build final arrays (index-based, always 12 months)
        $totalFirstTimers = [];
        $integratedFirstTimers = [];

        foreach (range(1, 12) as $month) {
            $totalFirstTimers[] = (int) ($firstTimersPerMonth[$month] ?? 0);
            $integratedFirstTimers[] = (int) ($integratedPerMonth[$month] ?? 0);
        }

        return $this->successResponse([
            'year' => (int) $year,
            'total_first_timers' => $totalFirstTimers,
            'integrated_first_timers' => $integratedFirstTimers,
        ], 'First timers analytics retrieved successfully');
    }
    public function assignFollowup(FirstTimer $firstTimer)
    {
        $candidate = $this->followupService->assignMemberToFirstTimer($firstTimer);

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
            $status = FollowUpStatus::where('slug', $payload['status'])->first();
            if (!$status) {
                return $this->errorResponse(null, 'Status not found', 404);
            }
        } else {
            $status = FollowUpStatus::find($payload['status_id']);
        }

        $firstTimer->follow_up_status_id = $status->id;
        $firstTimer->save();

        // Optionally: if the status is integrated or opt-out, unassign the member
        if (in_array($status->slug, ['integrated', 'opt-out'])) {
            $this->followupService->unassign($firstTimer);
        }

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
