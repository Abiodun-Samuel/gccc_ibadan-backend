<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\MarkAbsenteesRequest;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'service_id',
            'attendance_date',
            'status',
            'mode'
        ]);

        $attendance = $this->attendanceService->getAllAttendance($filters);

        return $this->successResponse(
            AttendanceResource::collection($attendance),
            'Attendance records retrieved successfully'
        );
    }

    public function markAttendance(MarkAttendanceRequest $request): JsonResponse
    {
        $attendance = $this->attendanceService->markUserAttendance(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse(
            new AttendanceResource($attendance),
            'Attendance marked successfully',
            Response::HTTP_CREATED
        );
    }

    public function history(Request $request): JsonResponse
    {
        $filters = $request->only([
            'service_id',
            'attendance_date',
            'status',
            'mode'
        ]);

        $history = $this->attendanceService->getUserAttendanceHistory(
            $request->user(),
            $filters
        );

        return $this->successResponse(
            AttendanceResource::collection($history),
            'Attendance history retrieved successfully'
        );
    }

    public function markAbsentees(MarkAbsenteesRequest $request): JsonResponse
    {
        $inserted = $this->attendanceService->markAbsentees($request->validated());

        return $this->successResponse(
            ['marked_absent_count' => $inserted],
            'All absent users have been marked',
            Response::HTTP_CREATED
        );
    }

    public function adminMarkAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'attendance_date' => 'required|date',
            'attendances' => 'required|array|min:1',
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => 'required|in:present,absent',
            'attendances.*.mode' => 'nullable|in:online,onsite',
        ]);

        $this->attendanceService->bulkMarkAttendance($validated);

        return $this->successResponse(
            [],
            'Attendance updated successfully for selected users'
        );
    }

    public function assignAbsenteesToLeaders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'attendance_date' => ['required', 'date'],
            'leader_ids' => ['required', 'array', 'min:1'],
            'leader_ids.*' => ['exists:users,id'],
        ]);

        $result = $this->attendanceService->assignAbsenteesToLeaders($validated);

        return $this->successResponse(
            $result,
            "{$result['assigned_count']} absent members assigned to {$result['leaders_count']} leaders successfully",
            Response::HTTP_CREATED
        );
    }

    public function getAdminAttendanceMonthlyStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $data = $this->attendanceService->getMonthlyAverageAttendance($validated['year'] ?? null);

        return $this->successResponse($data, 'Monthly statistics retrieved successfully');
    }

    public function getUserAttendanceMonthlyStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $metrics = $this->attendanceService->getUserAttendanceMetrics(
            $request->user(),
            $validated['month'] ?? null,
            $validated['year'] ?? null
        );

        return $this->successResponse(
            $metrics,
            'Attendance metrics retrieved successfully'
        );
    }
}
