<?php

namespace App\Http\Controllers;

use App\Config\PointRewards;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\PointService;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $service,
        private PointService $pointService,
    ) {}
    // ─── Public ───────────────────────────────────────────────────────────────

    /**
     * List all registrations with total count.
     * Public access.
     */
    public function index(): JsonResponse
    {
        $result = $this->service->all();

        return $this->successResponse(
            [
                'total_registered' => $result['total_registered'],
                'max_capacity'     => $result['max_capacity'],
                'available_slots'  => $result['available_slots'],
                'registrations'    => RegistrationResource::collection($result['registrations']),
            ],
            'Registrations fetched successfully.'
        );
    }

    /**
     * Register a new attendee (max 54).
     * Public access.
     */
    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $user =  $request->user();
        $registration = $this->service->register($request->validated());
        $this->pointService->award($user, PointRewards::EVENT_REGISTERED);

        return $this->successResponse(
            new RegistrationResource($registration),
            'Registration successful.',
            Response::HTTP_CREATED
        );
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    /**
     * Update a registration.
     * Admin only.
     */
    public function update(UpdateRegistrationRequest $request, Registration $registration): JsonResponse
    {
        $registration = $this->service->update($registration, $request->validated());

        return $this->successResponse(
            new RegistrationResource($registration),
            'Registration updated successfully.'
        );
    }

    /**
     * Delete a registration.
     * Admin only.
     */
    public function destroy(Registration $registration): JsonResponse
    {
        $this->service->delete($registration);

        return $this->successResponse(null, 'Registration deleted successfully.');
    }
}
