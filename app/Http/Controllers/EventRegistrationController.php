<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use App\Http\Requests\StoreEventRegistrationRequest;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'event' => ['required', 'string']
        ]);
        $user = $request->user();
        $registrations = $user->eventRegistrations()->where('event', $validated['event'])->with([
            'transactions',
            'user:id,first_name,last_name,avatar,gender,phone_number,email,address,community'
        ])
            ->latest()
            ->first();
        return $this->successResponse($registrations, 'Registration fetched successfully.',);
    }

    public function adminIndex()
    {
        $registrations = EventRegistration::with(['transactions', 'user:id,avatar,first_name,last_name,gender,phone_number,email,address,community'])
            ->latest()
            ->get();
        return $this->successResponse($registrations, 'Registration fetched successfully.',);
    }

    public function store(StoreEventRegistrationRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $registration = EventRegistration::create($data);
        return $this->successResponse($registration, 'Registration created successfully.',);
    }

    public function show(Request $request, EventRegistration $eventRegistration)
    {
        return $this->successResponse($eventRegistration, 'Registration created successfully.',);
    }

    public function update(StoreEventRegistrationRequest $request, EventRegistration $eventRegistration)
    {
        $eventRegistration->update($request->validated());
        return $this->successResponse($eventRegistration, 'Registration updated successfully.',);
    }

    public function destroy(EventRegistration $eventRegistration)
    {
        $eventRegistration->delete();
        return $this->successResponse('', 'Registration deleted successfully',);
    }
}
