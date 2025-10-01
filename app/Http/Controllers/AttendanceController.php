<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\AdminMarkAttendanceRequest;
use App\Http\Requests\Attendance\GetAbsenteesRequest;
use App\Http\Requests\Attendance\MarkAbsenteesRequest;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public $attendanceService;
    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Display a listing of attendance records with pagination
     */
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
     * Get attendance history for authenticated user
     */
    public function history(Request $request): JsonResponse
    {
        $history = $this->attendanceService->getUserAttendanceHistory(
            $request->user(),
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

    public function assignAbsenteesToLeaders(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date'],
        ]);


        return $this->successResponse('assignments', 'Absentees assigned to leaders', Response::HTTP_CREATED);
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
