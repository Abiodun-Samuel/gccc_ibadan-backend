<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PicnicRegistrationRequest;
use App\Http\Resources\PicnicRegistrationResource;
use App\Models\PicnicRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PicnicRegistrationController extends Controller
{
    public function register(PicnicRegistrationRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $currentYear = now()->year;

        $existingRegistration = PicnicRegistration::where('user_id', $userId)
            ->where('year', $currentYear)
            ->first();

        if (!$existingRegistration) {
            return response()->json([
                'message' => 'Registration is closed. The guest list is complete! Check back next year.',
                'available_slots' => 0,
                'max_capacity' => config('picnic.max_registrations_per_year'),
                'registration_status' => 'closed'
            ], 422);
        }

        if (!$existingRegistration && PicnicRegistration::isLimitReached($currentYear)) {
            return response()->json([
                'message' => 'Registration is full. We have reached the maximum capacity of 70 participants.',
                'available_slots' => 0,
                'max_capacity' => config('picnic.max_registrations_per_year')
            ], 422);
        }

        $registration = PicnicRegistration::updateOrCreate(
            [
                'user_id' => $userId,
                'year' => $currentYear
            ],
            [
                'games' => $request->games,
                'support_amount' => $request->support_amount,
                'registered_at' => $existingRegistration ? $existingRegistration->registered_at : now()
            ]
        );

        $registration->load('user');

        $message = $existingRegistration
            ? 'Registration updated successfully! Your changes have been saved.'
            : 'Registration successful! Your ticket will be sent via email 48 hours before the event.';

        return response()->json([
            'message' => $message,
            'is_update' => (bool) $existingRegistration,
            'available_slots' => PicnicRegistration::availableSlots($currentYear),
            'registration' => new PicnicRegistrationResource($registration)
        ], $existingRegistration ? 200 : 201);
    }

    /**
     * Get user's current registration
     */
    public function myRegistration(): JsonResponse
    {
        $registration = PicnicRegistration::with('user')
            ->where('user_id', auth()->id())
            ->currentYear()
            ->first();

        if (!$registration) {
            return response()->json([
                'registered' => false,
                'message' => 'You have not registered for this event.',
                'available_slots' => PicnicRegistration::availableSlots(now()->year),
                'max_capacity' => config('picnic.max_registrations_per_year')
            ], 404);
        }

        return response()->json([
            'registered' => true,
            'registration' => new PicnicRegistrationResource($registration)
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);

        // Get all registrations with users
        $registrations = PicnicRegistration::with('user:id,first_name,last_name,email,phone_number')
            ->forYear($year)
            ->orderBy('registered_at', 'asc')
            ->get();

        // Build game groups with ONE coordinator per user rule
        $gameGroups = $this->buildGameGroups($registrations);

        // Calculate basic statistics
        $statistics = [
            'total_registrations' => $registrations->count(),
            'available_slots' => PicnicRegistration::availableSlots($year),
            'max_capacity' => config('picnic.max_registrations_per_year'),
            'capacity_percentage' => round(($registrations->count() / config('picnic.max_registrations_per_year')) * 100, 2),
            'total_support' => (float) number_format($registrations->sum('support_amount'), 2, '.', ''),
        ];

        return response()->json([
            'year' => $year,
            'statistics' => $statistics,
            'game_groups' => $gameGroups,
            'registrations' => PicnicRegistrationResource::collection($registrations)
        ]);
    }

    /**
     * Build game groups with ONE coordinator per user rule
     *
     * FIXED: Uses centralized coordinator determination logic
     */
    private function buildGameGroups($registrations): array
    {
        $games = [
            'Checkers',
            'Card games',
            'Ludo',
            'Monopoly',
            'Scrabble',
            'Chess',
            'Jenga',
            'Snake and ladder',
            'Ayo'
        ];

        // CRITICAL: Determine coordinators ONCE for all games
        $coordinatorAssignments = $this->determineCoordinators($registrations, $games);

        $gameGroups = [];

        foreach ($games as $game) {
            // Get all registrations for this game
            $gameRegistrations = $registrations
                ->filter(fn($reg) => in_array($game, $reg->games))
                ->sortBy([
                    ['registered_at', 'asc'],
                    ['id', 'asc']
                ])
                ->values();

            // Get coordinator from global assignments
            $coordinatorId = $coordinatorAssignments[$game] ?? null;
            $coordinatorRegistration = $coordinatorId
                ? $gameRegistrations->firstWhere('user_id', $coordinatorId)
                : null;

            // Build coordinator object
            $coordinator = null;
            if ($coordinatorRegistration) {
                $coordinator = [
                    'id' => $coordinatorRegistration->user->id,
                    'name' => $coordinatorRegistration->user->first_name . ' ' . $coordinatorRegistration->user->last_name,
                    'email' => $coordinatorRegistration->user->email,
                    'phone_number' => $coordinatorRegistration->user->phone_number,
                    'registered_at' => $coordinatorRegistration->registered_at->toISOString(),
                    'is_coordinator' => true,
                ];
            }

            // Build members list with coordinator flag
            $members = $gameRegistrations
                ->map(function ($reg) use ($coordinatorId) {
                    return [
                        'id' => $reg->user->id,
                        'name' => $reg->user->first_name . ' ' . $reg->user->last_name,
                        'email' => $reg->user->email,
                        'phone_number' => $reg->user->phone_number,
                        'registered_at' => $reg->registered_at->toISOString(),
                        'is_coordinator' => $reg->user_id === $coordinatorId,
                    ];
                })
                ->values()
                ->toArray();

            $gameGroups[] = [
                'game' => $game,
                'total_members' => $gameRegistrations->count(),
                'coordinator' => $coordinator,
                'members' => $members,
            ];
        }

        // Sort by total members descending
        usort($gameGroups, fn($a, $b) => $b['total_members'] <=> $a['total_members']);

        return $gameGroups;
    }

    /**
     * Determine coordinator assignments across ALL games
     *
     * This is the SINGLE SOURCE OF TRUTH for coordinator assignments.
     * Ensures one coordinator per user across all games.
     *
     * @param \Illuminate\Support\Collection $registrations
     * @param array $games
     * @return array [game_name => user_id]
     */
    private function determineCoordinators($registrations, array $games): array
    {
        $coordinatorAssignments = [];
        $assignedCoordinators = [];

        foreach ($games as $game) {
            // Get all registrations for this game, sorted by time
            $gameRegistrations = $registrations
                ->filter(fn($reg) => in_array($game, $reg->games))
                ->sortBy([
                    ['registered_at', 'asc'],
                    ['id', 'asc']
                ])
                ->values();

            // Find first person who isn't already a coordinator
            $coordinatorId = null;

            foreach ($gameRegistrations as $reg) {
                if (!in_array($reg->user_id, $assignedCoordinators)) {
                    $coordinatorId = $reg->user_id;
                    $assignedCoordinators[] = $coordinatorId;
                    break;
                }
            }

            // Fallback: if everyone is already a coordinator, use first person
            if (!$coordinatorId && $gameRegistrations->count() > 0) {
                $coordinatorId = $gameRegistrations->first()->user_id;
            }

            $coordinatorAssignments[$game] = $coordinatorId;
        }

        return $coordinatorAssignments;
    }
}
