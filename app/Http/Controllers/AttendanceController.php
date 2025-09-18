<?php

namespace App\Http\Controllers;

use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * fetch all attendance records
     */
    public function index()
    {
        $attendance = Attendance::get();
        return $this->successResponse(AttendanceResource::collection($attendance), '', 200);
    }

    /**
     * Mark attendence for an auth user
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'mode' => 'required|in:onsite,online',
            'status' => 'required|in:present,absent',
        ]);

        $user = $request->user();
        $service = Service::findOrFail($request->service_id);

        // Determine today’s date
        $today = Carbon::now('Africa/Lagos')->toDateString();

        // Check service schedule
        $serviceDate = $service->date ?? $today; // recurring = today
        $serviceTime = Carbon::parse($service->start_time, 'Africa/Lagos');
        $now = Carbon::now('Africa/Lagos');

        // ✅ Allow marking within 2 hours of start time
        // if (!$now->between($serviceTime, $serviceTime->copy()->addHours(4))) {
        //     return $this->errorResponse(null, "Attendance can only be marked within 4 hours of service time.", 403);
        // }

        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
                'attendance_date' => $serviceDate,
            ],
            [
                'status' => $request->status,
                'mode' => $request->mode,
            ]
        );
        return $this->successResponse($attendance, "Attendance marked successfully.", 201);
    }

    public function adminMarkAttendance(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'attendance_date' => 'required|date',
            'attendances' => 'required|array|min:1',
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => 'required|in:present,absent',
            'attendances.*.mode' => 'nullable|in:online,onsite',
        ]);

        $attendanceDate = Carbon::parse($validated['attendance_date'])->toDateString();
        $service = Service::findOrFail($validated['service_id']);

        DB::beginTransaction();
        try {
            foreach ($validated['attendances'] as $att) {
                Attendance::updateOrCreate(
                    [
                        'user_id' => $att['user_id'],
                        'service_id' => $service->id,
                        'attendance_date' => $attendanceDate,
                    ],
                    [
                        'status' => $att['status'],
                        'mode' => $att['mode'],
                    ]
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'Attendance updated successfully for selected users',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating attendance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Fetch authenticated user attendance history
     */
    public function history(Request $request)
    {
        $user = $request->user();
        $history = $user->attendances()
            ->with('service')
            ->orderBy('attendance_date', 'desc')
            ->get();
        return $this->successResponse(AttendanceResource::collection($history), '', 200);
    }

    /**
     *   Mark all absent users absent for a service
     */
    public function markAbsentees(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
        ]);

        $serviceId = $validated['service_id'];
        $attendanceDate = Carbon::parse($validated['date'], 'Africa/Lagos')->toDateString();

        $alreadyMarked = Attendance::where('service_id', $serviceId)
            ->whereDate('attendance_date', $attendanceDate)
            ->select('user_id');

        $inserted = DB::table('attendances')->insertUsing(
            ['user_id', 'service_id', 'attendance_date', 'status', 'mode', 'created_at', 'updated_at'],
            DB::table('users')
                ->whereNotIn('id', $alreadyMarked)
                ->selectRaw("id as user_id, {$serviceId} as service_id, '{$attendanceDate}' as attendance_date, 'absent' as status, 'online' as mode, NOW() as created_at, NOW() as updated_at")
        );
        return $this->successResponse($inserted, 'all absent users marked are marked', 201);
    }

    /**
     *   Fetch all absent users for a service
     */
    public function getAbsentees(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
        ]);

        $attendanceDate = Carbon::parse($validated['date'], 'Africa/Lagos')->toDateString();

        $absentees = Attendance::with('user')
            ->where('service_id', $validated['service_id'])
            ->whereDate('attendance_date', $attendanceDate)
            ->where('status', 'absent')
            ->get();

        $csvFile = storage_path("app/absentees_{$attendanceDate}.csv");
        $handle = fopen($csvFile, 'w');

        // Add header row
        fputcsv($handle, ['First Name', 'Last Name', 'Email', 'Phone Number', 'Service', 'Date']);

        // Add rows
        foreach ($absentees as $attendance) {
            fputcsv($handle, [
                $attendance->user->first_name,
                $attendance->user->last_name,
                $attendance->user->email,
                $attendance->user->phone_number,
                $attendance->service->name,
                $attendanceDate,
            ]);
        }
        fclose($handle);
        $data = AttendanceResource::collection($absentees);
        return $this->successResponse($data, 'All absent member fetched successfully', 200);
    }
}
