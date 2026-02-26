<?php

namespace App\Http\Controllers;

use App\Config\PointRewards;
use App\Http\Requests\StoreUsherAttendanceRequest;
use App\Http\Requests\UpdateUsherAttendanceRequest;
use App\Http\Resources\UsherAttendanceResource;
use App\Models\UsherAttendance;
use App\Services\PointService;
use App\Services\UsherAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsherAttendanceController extends Controller
{
    public function __construct(
        private readonly UsherAttendanceService $service,
        private PointService $pointService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $attendances = $this->service->getAllAttendances();
        $chartData   = $this->service->getChartData(
            $request->integer('year', now()->year)
        );

        return $this->successResponse(
            [
                'attendances' => UsherAttendanceResource::collection($attendances),
                'chart'       => $chartData,
            ],
            'Attendances retrieved successfully'
        );
    }

    public function store(StoreUsherAttendanceRequest $request): JsonResponse
    {
        $user =  $request->user();
        $attendance = $this->service->store($request->validated());
        $this->pointService->award($user, PointRewards::USHER_ATTENDANCE_MARKED);
        return $this->successResponse(new UsherAttendanceResource($attendance), 'Attendance recorded successfully', Response::HTTP_CREATED);
    }

    public function show(UsherAttendance $usherAttendance): JsonResponse
    {
        return $this->successResponse(new UsherAttendanceResource($usherAttendance), 'Attendance retrieved successfully');
    }

    public function update(UpdateUsherAttendanceRequest $request, UsherAttendance $usherAttendance): JsonResponse
    {
        $attendance = $this->service->update($usherAttendance, $request->validated());
        return $this->successResponse(new UsherAttendanceResource($attendance), 'Attendance updated successfully');
    }

    public function destroy(UsherAttendance $usherAttendance): JsonResponse
    {
        $this->service->delete($usherAttendance);
        return $this->successResponse(null, 'Attendance deleted successfully', Response::HTTP_NO_CONTENT);
    }
}
