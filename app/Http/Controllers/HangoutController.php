<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHangoutRegistrationRequest;
use App\Models\HangoutRegistration;
use App\Services\HangoutRegistrationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public side of the GCCC Hangout: what the page needs, and registration.
 */
class HangoutController extends Controller
{
    public function __construct(private readonly HangoutRegistrationService $service) {}

    /**
     * GET /api/hangout
     */
    public function show(): JsonResponse
    {
        // Open games only, in order, with how many have picked each so far.
        $open = $this->service->gameNames();
        $games = collect($this->service->countsByGame())
            ->filter(fn(array $row) => in_array($row['game'], $open, true))
            ->map(fn(array $row) => ['name' => $row['game'], 'registrations_count' => $row['count']])
            ->values();

        return $this->successResponse([
            'title'                  => config('hangout.title'),
            'starts_at'              => $this->service->startsAt()->toIso8601String(),
            'registration_open'      => $this->service->isOpen(),
            'registration_closes_at' => $this->service->closesAt()->toIso8601String(),
            'registrations_count'    => HangoutRegistration::count(),
            'games'                  => $games,
            'support_account'        => config('hangout.support_account'),
        ], 'Hangout details retrieved successfully.');
    }

    /**
     * POST /api/hangout/registrations
     *
     * Registers, or updates the registration already using this email. The
     * reply only confirms what was just sent: an update must not reveal the
     * saved registration to whoever typed the email.
     */
    public function register(StoreHangoutRegistrationRequest $request): JsonResponse
    {
        if (!$this->service->isOpen()) {
            return $this->errorResponse('Registration for the hangout has closed.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$registration, $created] = $this->service->register($request->validated());

        return $this->successResponse(
            [
                'updated'   => !$created,
                'full_name' => $registration->full_name,
                'game'      => $registration->game,
            ],
            $created ? "You're registered for the hangout." : 'Your registration has been updated.',
            $created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
