<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\FollowUpRequest;
use App\Http\Requests\StoreFirstTimerRequest;
use App\Http\Requests\UpdateFirstTimerRequest;
use App\Http\Resources\FirstTimerResource;
use App\Http\Resources\FollowupFeedbackResource;
use App\Http\Resources\FollowUpResource;
use App\Models\FirstTimer;
use App\Models\FollowUpStatus;
use App\Services\FirstTimerService;
use App\Services\FollowUpService;
use App\Services\MailService;
use App\Services\UploadService;
use Cache;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class FirstTimerController extends Controller
{
    protected MailService $mailService;
    public $firstTimerService;
    public $uploadService;
    public $followUpService;
    public function __construct(FirstTimerService $firstTimerService, MailService $mailService, FollowUpService $followUpService, UploadService $uploadService)
    {
        $this->firstTimerService = $firstTimerService;
        $this->mailService = $mailService;
        $this->followUpService = $followUpService;
        $this->uploadService = $uploadService;
    }

    public function index()
    {
        $firstTimers = FirstTimer::with(['followUpStatus', 'assignedTo'])->orderBy('date_of_visit', 'desc')->get();
        return $this->successResponse(FirstTimerResource::collection($firstTimers), 'First timers retrieved successfully', Response::HTTP_OK);
    }

    public function store(StoreFirstTimerRequest $request)
    {
        $data = $request->validated();
        $followupMember = $this->firstTimerService->findLeastLoadedFollowupMember($data['gender']);

        $firstTimer = FirstTimer::create(array_merge($data, [
            'assigned_to_member_id' => optional($followupMember)->id,
            'follow_up_status_id' => FollowUpStatus::NOT_CONTACTED_ID,
            'assigned_at' => now(),
            'week_ending' => getNextSunday()?->toDateString()
        ]));
        $firstTimer->load(['followUpStatus', 'assignedTo']);
        $data = new FirstTimerResource($firstTimer);

        return $this->successResponse($data, 'First timer created successfully', Response::HTTP_CREATED);
    }

    public function setFollowupStatus(Request $request)
    {
        $payload = $request->validate([
            'first_timer_ids' => ['required', 'array'],
            'first_timer_ids.*' => ['integer', 'exists:first_timers,id'],
            'status_id' => ['required', 'integer', 'exists:follow_up_statuses,id'],
        ]);

        FirstTimer::whereIn('id', $payload['first_timer_ids'])
            ->update(['follow_up_status_id' => $payload['status_id']]);

        $updatedFirstTimers = FirstTimer::with('followUpStatus', 'assignedTo')
            ->whereIn('id', $payload['first_timer_ids'])
            ->get();

        return $this->successResponse(FirstTimerResource::collection($updatedFirstTimers), 'Statuses updated successfully', Response::HTTP_OK);
    }

    public function show(FirstTimer $firstTimer): JsonResponse
    {
        try {
            $firstTimer->load(['followUpStatus', 'assignedTo']);
            $data = new FirstTimerResource($firstTimer);
            return $this->successResponse(
                $data,
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

    public function update(UpdateFirstTimerRequest $request, FirstTimer $firstTimer): JsonResponse
    {
        try {
            $validated = $request->validated();
            if (!empty($validated['avatar'])) {
                try {
                    if ($firstTimer->avatar != null) {
                        $this->uploadService->delete($firstTimer->avatar);
                    }
                    $validated['avatar'] = $this->uploadService->upload(
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

    public function getFirsttimersAssigned(Request $request): JsonResponse
    {
        $member = $request->user();
        $firstTimers = $this->firstTimerService->getFirstTimersAssigned($member->id);
        return $this->successResponse(FirstTimerResource::collection($firstTimers), 'First timers retrieved successfully', Response::HTTP_OK);
    }

    // Admin
    public function getFirstTimersAnalytics(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $cacheKey = "first_timers_analytics_{$year}";
        $data = Cache::remember($cacheKey, now()->addDay(), function () use ($year) {
            // Predefine month names
            $monthNames = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December',
            ];

            // Fetch all statuses once (id => title)
            $statuses = FollowUpStatus::pluck('title', 'id');

            // Fetch the ID for "Integrated" status
            $integratedStatusId = FollowUpStatus::where('title', FollowUpStatusEnum::INTEGRATED->value)->value('id');

            /**
             * Optimized single query:
             * - Get total counts
             * - Get integrated counts
             * - Group by month + follow_up_status_id
             */
            $results = DB::table('first_timers')
                ->selectRaw('
                MONTH(date_of_visit) as month,
                follow_up_status_id,
                COUNT(*) as total,
                SUM(CASE WHEN follow_up_status_id = ? THEN 1 ELSE 0 END) as integrated_count
            ', [$integratedStatusId])
                ->whereYear('date_of_visit', $year)
                ->groupBy(DB::raw('MONTH(date_of_visit), follow_up_status_id'))
                ->get();

            /**
             * Initialize data structures
             */
            $statusPerMonth = [];
            $monthlyTotals = array_fill(1, 12, 0);
            $monthlyIntegrated = array_fill(1, 12, 0);

            // Initialize rows for each month
            foreach (range(1, 12) as $month) {
                $statusRow = ['month' => $monthNames[$month]];
                foreach ($statuses as $statusTitle) {
                    $statusRow[$statusTitle] = 0;
                }
                $statusPerMonth[$month] = $statusRow;
            }

            /**
             * Fill structures with DB data
             */
            foreach ($results as $row) {
                $month = (int) $row->month;

                // Update totals
                $monthlyTotals[$month] += $row->total;

                // Update integrated totals
                if ($row->follow_up_status_id == $integratedStatusId) {
                    $monthlyIntegrated[$month] += $row->total;
                }

                // Update per-status count
                $statusTitle = $statuses[$row->follow_up_status_id] ?? 'Unknown';
                $statusPerMonth[$month][$statusTitle] = (int) $row->total;
            }

            $totalFirstTimers = [];
            $integratedFirstTimers = [];

            foreach (range(1, 12) as $month) {
                $totalFirstTimers[] = [
                    'month' => $monthNames[$month],
                    'count' => $monthlyTotals[$month],
                ];

                $integratedFirstTimers[] = [
                    'month' => $monthNames[$month],
                    'count' => $monthlyIntegrated[$month],
                ];
            }

            return [
                'year' => $year,
                'statusesPerMonth' => array_values($statusPerMonth),
                'totalFirstTimers' => $totalFirstTimers,
                'integratedFirstTimers' => $integratedFirstTimers,
            ];
        });

        return $this->successResponse($data, 'First timers analytics retrieved successfully', Response::HTTP_OK);
    }

    public function sendFirstTimerWelcomeEmail(FirstTimer $firstTimer, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255'
        ]);
        DB::beginTransaction();
        try {
            $updatedFirstTimer = $this->firstTimerService->updateFirstTimer(
                $firstTimer,
                ['follow_up_status_id' => FollowUpStatus::CONTACTED_ID]
            );

            $recipients = [
                [
                    'name' => $validated['name'],
                    'email' => $validated['email']
                ]
            ];
            // Send welcome email
            $this->mailService->sendFirstTimerWelcomeEmail($recipients);

            DB::commit();

            return $this->successResponse(
                new FirstTimerResource($updatedFirstTimer),
                'Welcome email sent successfully',
                Response::HTTP_OK
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }

    public function getFirstTimersWithFollowups()
    {
        $firstTimers = FirstTimer::with([
            'followUpStatus',
            'assignedTo',
            'followupFeedbacks.user' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'avatar');
            }
        ])->where('status', 'active')->orderBy('date_of_visit', 'desc')->get();
        return $this->successResponse(FirstTimerResource::collection($firstTimers), 'First timers retrieved successfully', Response::HTTP_OK);
    }
}
