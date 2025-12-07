<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\Attendance;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::get();
        return ServiceResource::collection($services);
    }
    public function today(Request $request)
    {
        $now = Carbon::now('Africa/Lagos');
        $today = strtolower($now->format('l'));

        $service = $this->findTodaysService($today, $now);

        if (!$service) {
            return $this->errorResponse('No service scheduled for today.', 404);
        }

        $user = $request->user();
        $serviceDateTime = $this->getServiceDateTime($service, $now);
        $serviceStatus = $this->determineServiceStatus($serviceDateTime, $now);

        $attendance = $this->getUserAttendance($user, $service->id, $now);

        $response = [
            'service' => new ServiceResource($service),
            'birthday_list' => User::birthdayThisWeek()->get(), // User::members()->birthdayThisWeek()->get(),
            'service_status' => $serviceStatus['status'],
            'can_mark' => $this->canMarkAttendance($serviceStatus['status'], $attendance),
        ];

        if ($serviceStatus['status'] === 'upcoming') {
            $response['seconds_until_start'] = $serviceStatus['seconds_until_start'];
        }

        if ($attendance) {
            $response['attendance'] = [
                'status' => $attendance->status,
                'mode' => $attendance->mode,
                'marked_at' => $attendance->created_at->toDateTimeString(),
            ];
        }

        return $this->successResponse($response, 'Service retrieved successfully', 200);
    }

    private function findTodaysService(string $dayOfWeek, Carbon $now): ?Service
    {
        return Service::where(function ($query) use ($dayOfWeek, $now) {
            $query->where(function ($q) use ($dayOfWeek) {
                $q->where('is_recurring', true)
                    ->where('day_of_week', $dayOfWeek);
            })->orWhere(function ($q) use ($now) {
                $q->where('is_recurring', false)
                    ->whereDate('service_date', $now->toDateString());
            });
        })->first();
    }


    private function getServiceDateTime(Service $service, Carbon $now): Carbon
    {
        if ($service->is_recurring) {
            $dateString = $now->format('Y-m-d');
            $timeString = $service->start_time;

            if (strlen($timeString) <= 8) {
                return Carbon::parse("{$dateString} {$timeString}", 'Africa/Lagos');
            } else {
                $time = Carbon::parse($timeString)->format('H:i:s');
                return Carbon::parse("{$dateString} {$time}", 'Africa/Lagos');
            }
        }

        $dateString = Carbon::parse($service->service_date)->format('Y-m-d');
        $timeString = $service->start_time;

        if (strlen($timeString) <= 8) {
            return Carbon::parse("{$dateString} {$timeString}", 'Africa/Lagos');
        } else {
            $time = Carbon::parse($timeString)->format('H:i:s');
            return Carbon::parse("{$dateString} {$time}", 'Africa/Lagos');
        }
    }

    private function determineServiceStatus(Carbon $serviceDateTime, Carbon $now): array
    {
        $diffInSeconds = $now->diffInSeconds($serviceDateTime, false);
        $diffInHours = $now->diffInHours($serviceDateTime, false);

        if ($diffInHours <= -6) {
            return ['status' => 'ended'];
        }

        if ($diffInSeconds <= 0 && $diffInHours > -6) {
            return ['status' => 'ongoing'];
        }
        $secondsRemaining = (int) abs($diffInSeconds);
        return [
            'status' => 'upcoming',
            'seconds_until_start' => $secondsRemaining,
        ];
    }

    private function getUserAttendance($user, int $serviceId, Carbon $now): ?Attendance
    {
        if (!$user) {
            return null;
        }

        return $user->attendances()
            ->where('service_id', $serviceId)
            ->whereDate('attendance_date', $now->toDateString())
            ->first();
    }

    private function canMarkAttendance(string $serviceStatus, ?Attendance $attendance): bool
    {
        if ($attendance) {
            return false;
        }
        return $serviceStatus === 'ongoing';
    }
}
