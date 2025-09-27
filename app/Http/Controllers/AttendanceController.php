<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\AdminMarkAttendanceRequest;
use App\Http\Requests\Attendance\GetAbsenteesRequest;
use App\Http\Requests\Attendance\MarkAbsenteesRequest;
use App\Http\Requests\MarkAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {
    }

    /**
     * Display a listing of attendance records with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $attendance = $this->attendanceService->getAllAttendance($perPage);

        return $this->paginatedResponse(
            $attendance,
            'Attendance records retrieved successfully'
        );
    }

    /**
     * Mark attendance for the authenticated user
     */
    public function markAttendance(MarkAttendanceRequest $request): JsonResponse
    {
        $attendance = $this->attendanceService->markUserAttendance(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse(
            new AttendanceResource($attendance),
            'Attendance marked successfully',
            201
        );
    }

    /**
     * Admin bulk mark attendance for multiple users
     */
    public function adminMarkAttendance(AdminMarkAttendanceRequest $request): JsonResponse
    {
        $this->attendanceService->adminMarkAttendance($request->validated());

        return $this->successResponse(
            null,
            'Attendance updated successfully for selected users'
        );
    }

    /**
     * Get attendance history for authenticated user
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $history = $this->attendanceService->getUserAttendanceHistory(
            $request->user(),
            $perPage
        );

        return $this->paginatedResponse(
            $history,
            'Attendance history retrieved successfully'
        );
    }

    /**
     * Mark all non-present users as absent for a specific service and date
     */
    public function markAbsentees(MarkAbsenteesRequest $request): JsonResponse
    {
        $inserted = $this->attendanceService->markAbsentees($request->validated());

        return $this->successResponse(
            ['marked_absent_count' => $inserted],
            'All absent users have been marked',
            201
        );
    }

    /**
     * Get list of absentees for a specific service and date
     */
    public function getAbsentees(GetAbsenteesRequest $request): JsonResponse
    {
        $absentees = $this->attendanceService->getAbsentees($request->validated());

        return $this->successResponse(
            AttendanceResource::collection($absentees),
            'Absentees retrieved successfully'
        );
    }

    public function getAdminAttendanceMonthlyStats(Request $request)
    {
        $mode = $request->get('mode', 'avg'); // avg | total
        $year = $request->get('year', 2025);        // optional

        try {
            $data = $this->attendanceService->getMonthlyStats($mode, $year);
            return $this->successResponse($data, '');
        } catch (\InvalidArgumentException $e) {
            return $this->successResponse(null, $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
