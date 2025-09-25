<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsherAttendanceRequest;
use App\Http\Requests\UpdateUsherAttendanceRequest;
use App\Http\Resources\UsherAttendanceResource;
use App\Models\UsherAttendance;
use App\Services\UsherAttendanceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UsherAttendanceController extends Controller
{
    public function __construct(
        private readonly UsherAttendanceService $service
    ) {
    }

    public function index(): JsonResponse
    {
        $attendances = $this->service->getAllAttendances();
        return $this->successResponse(UsherAttendanceResource::collection($attendances), 'Attendances retrieved successfully');
    }

    public function store(StoreUsherAttendanceRequest $request): JsonResponse
    {
        $attendance = $this->service->store($request->validated());
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
