<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupHangoutRegistrationRequest;
use App\Http\Requests\StoreHangoutRegistrationRequest;
use App\Models\HangoutRegistration;
use App\Services\HangoutRegistrationService;
use App\Services\PaintballGroupService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public side of the GCCC Hangout: what the page needs, registration, and
 * members finding their own registration to update it.
 */
class HangoutController extends Controller
{
    public function __construct(
        private readonly HangoutRegistrationService $service,
        private readonly PaintballGroupService $paintball,
    ) {}

    /**
     * GET /api/hangout
     *
     * No prices here: the cost estimate is for admins only.
     */
    public function show(): JsonResponse
    {
        // Open games only, in order, with how many people (adults and children)
        // have picked each so far, and which only children can pick.
        $open = $this->service->gameNames();
        $childrenOnly = $this->service->childrenOnlyGames();
        $games = collect($this->service->countsByGame())
            ->filter(fn(array $row) => in_array($row['game'], $open, true))
            ->map(fn(array $row) => [
                'name'                => $row['game'],
                'registrations_count' => $row['count'],
                'children_only'       => in_array($row['game'], $childrenOnly, true),
            ])
            ->values();

        return $this->successResponse([
            'title'                  => config('hangout.title'),
            'starts_at'              => $this->service->startsAt()->toIso8601String(),
            'registration_open'      => $this->service->isOpen(),
            'registration_closes_at' => $this->service->closesAt()->toIso8601String(),
            'registrations_count'    => HangoutRegistration::count(),
            'games'                  => $games,
            'menus'                  => array_map(fn(string $name) => ['name' => $name], $this->service->menuNames()),
            'max_children'           => $this->service->maxChildren(),
            'support_account'        => config('hangout.support_account'),
        ], 'Hangout details retrieved successfully.');
    }

    /**
     * POST /api/hangout/registrations
     *
     * Registers, or updates the registration already using this email when
     * the phone number it was saved with is given (see the service).
     */
    public function register(StoreHangoutRegistrationRequest $request): JsonResponse
    {
        if (!$this->service->isOpen()) {
            return $this->errorResponse('Registration for the hangout has closed.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$registration, $created] = $this->service->register($request->validated());

        return $this->successResponse(
            ['updated' => !$created, 'registration' => $this->details($registration)],
            $created ? "You're registered for the hangout." : 'Your registration has been updated.',
            $created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    /**
     * POST /api/hangout/registrations/lookup
     *
     * A member's own registrations, found by the email or the phone number
     * they registered with (both, when both are given). `registrations` has
     * one for an email; a shared phone number can find several, newest first.
     */
    public function lookup(LookupHangoutRegistrationRequest $request): JsonResponse
    {
        $registrations = $this->service->lookup($request->validated('email'), $request->validated('phone_number'));

        if ($registrations->isEmpty()) {
            return $this->errorResponse(
                "We couldn't find a registration with that email or phone number. Check it, or register below.",
                Response::HTTP_NOT_FOUND,
            );
        }

        // The groups are read once for all the registrations found.
        $groups = $this->paintball->groups();

        return $this->successResponse(
            ['registrations' => $registrations->map(fn(HangoutRegistration $registration) => $this->details($registration, $groups))->all()],
            $registrations->count() === 1 ? 'Registration found.' : 'Registrations found.',
        );
    }

    /**
     * What a member sees of their own registration, including the paintball
     * groups they (or their children) are in, with the names in each group.
     */
    private function details(HangoutRegistration $registration, ?array $allGroups = null): array
    {
        $groups = $this->paintball->forRegistration($registration, $allGroups);

        return [
            'full_name'       => $registration->full_name,
            'gender'          => $registration->gender,
            'email'           => $registration->email,
            'phone_number'    => $registration->phone_number,
            'whatsapp_number' => $registration->whatsapp_number,
            'game'            => $registration->game,
            'menu'            => $registration->menu,
            'support_amount'  => $registration->support_amount !== null ? (float) $registration->support_amount : null,
            'children'        => $registration->children
                ->map(fn($child) => $child->only(['full_name', 'gender', 'game', 'menu']))
                ->values()
                ->all(),
            // Null when nobody on this registration plays paintball. A player
            // keeps their group; `final` says registration has closed.
            'paintball'       => $groups ? [
                'final'          => !$this->service->isOpen(),
                'min_group_size' => $this->paintball->minGroupSize(),
                'groups'         => $groups,
            ] : null,
        ];
    }
}
