<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\Attendance;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $services = Service::get();
        return ServiceResource::collection($services);
    }

    public function today(Request $request)
    {
        $now = Carbon::now('Africa/Lagos');
        $today = strtolower($now->format('l'));

        // 1. Check recurring service
        $service = Service::where('is_recurring', true)
            ->where('day_of_week', $today)
            ->first();

        // 2. If no recurring service, check custom one-time service
        if (!$service) {
            $service = Service::where('is_recurring', false)
                ->whereDate('service_date', $now->toDateString())
                ->first();
        }

        if (!$service) {
            return $this->errorResponse(null, 'No service scheduled for today.', 404);
        }

        $user = $request?->user();
        $attendance = Attendance::where('user_id', $user?->id)
            ->where('service_id', $service?->id)
            ->whereDate('attendance_date', $now->toDateString())
            ->first();

        $response = [
            'service' => new ServiceResource($service),
            'can_mark' => !$attendance,
        ];

        if ($attendance) {
            $response['status'] = $attendance->status;
            $response['mode'] = $attendance->mode;
            $response['marked_at'] = $attendance->created_at->toDateTimeString();
        }

        return $this->successResponse($response, 'Service available', 200);
    }

    // public function today(Request $request)
    // {
    //     $now = Carbon::now('Africa/Lagos');
    //     $today = strtolower($now->format('l'));

    //     // 1. Check recurring service
    //     $service = Service::where('is_recurring', true)
    //         ->where('day_of_week', $today)
    //         ->first();

    //     // 2. If no recurring service, check custom one-time service
    //     if (!$service) {
    //         $service = Service::where('is_recurring', false)
    //             ->whereDate('service_date', $now->toDateString())
    //             ->first();
    //     }

    //     if (!$service) {
    //         return $this->errorResponse(null, 'No service scheduled for today.', 404);
    //     }

    //     // Get attendance window from query params (default: 2 hours before/after)
    //     $before = (int) $request->query('before', 2); // hours before service
    //     $after = (int) $request->query('after', 2);  // hours after service

    //     $serviceStart = Carbon::parse($service->start_time, 'Africa/Lagos')
    //         ->setDate($now->year, $now->month, $now->day);

    //     $windowStart = $serviceStart->copy()->subHours($before);
    //     $windowEnd = $serviceStart->copy()->addHours($after);

    //     if (!$now->between($windowStart, $windowEnd)) {
    //         return $this->errorResponse(null, 'Attendance not allowed at this time.', 403);
    //     }

    //     return $this->successResponse(new ServiceResource($service), 'Service available', 200);
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
