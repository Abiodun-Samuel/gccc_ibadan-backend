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

        // Check existing registration
        $existingRegistration = PicnicRegistration::where('user_id', $userId)
            ->where('year', $currentYear)
            ->first();

        // If creating new registration, check if limit reached
        if (!$existingRegistration && PicnicRegistration::isLimitReached($currentYear)) {
            return response()->json([
                'message' => 'Registration is full. We have reached the maximum capacity of 70 participants.',
                'available_slots' => 0,
                'max_capacity' => config('picnic.max_registrations_per_year')
            ], 422);
        }

        // Create or update registration
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
            ->orderBy('registered_at', 'desc')
            ->get();

        // Build game groups with coordinator assignment (first person alphabetically)
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
            'registrations' => $registrations->map(function ($reg) {
                return [
                    'id' => $reg->id,
                    'user' => [
                        'id' => $reg->user->id,
                        'name' => $reg->user->first_name . ' ' . $reg->user->last_name,
                        'email' => $reg->user->email,
                        'phone_number' => $reg->user->phone_number,
                    ],
                    'games' => $reg->games,
                    'support_amount' => $reg->support_amount ? (float) $reg->support_amount : null,
                    'registered_at' => $reg->registered_at->toISOString(),
                ];
            })
        ]);
    }

    /**
     * Build game groups with members and auto-assigned coordinators
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

        $gameGroups = [];

        foreach ($games as $game) {
            // Get all members who selected this game
            $members = $registrations
                ->filter(fn($reg) => in_array($game, $reg->games))
                ->map(fn($reg) => [
                    'id' => $reg->user->id,
                    'name' => $reg->user->first_name . ' ' . $reg->user->last_name,
                    'email' => $reg->user->email,
                    'phone_number' => $reg->user->phone_number,
                    'registered_at' => $reg->registered_at->toISOString(),
                ])
                ->sortBy('first_name') // Sort alphabetically
                ->values()
                ->toArray();

            // Assign first person (alphabetically) as coordinator
            $coordinator = !empty($members) ? array_merge($members[0], ['is_coordinator' => true]) : null;

            // Mark coordinator in members list
            if ($coordinator) {
                $members[0]['is_coordinator'] = true;
            }

            $gameGroups[] = [
                'game' => $game,
                'total_members' => count($members),
                'coordinator' => $coordinator,
                'members' => $members,
            ];
        }

        // Sort by total members descending
        usort($gameGroups, fn($a, $b) => $b['total_members'] <=> $a['total_members']);

        return $gameGroups;
    }
}
