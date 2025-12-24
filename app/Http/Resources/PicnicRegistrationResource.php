<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PicnicRegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'games' => $this->games,
            'support_amount' => $this->support_amount,
            'seat_number' => $this->id,
            'registered_at' => $this->registered_at,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
                'email' => $this->user->email,
                'phone_number' => $this->user->phone_number ?? null,
            ],
            // Enriched games with players and coordinator status visible to all
            'games_details' => $this->getGamesDetails(),
        ];
    }

    /**
     * Get enriched game details with coordinator visible to everyone
     *
     * FIXED: One coordinator per user rule
     *
     * Key insight: A user coordinates the FIRST game they registered for
     * where they are the earliest registrant who hasn't already been assigned
     * coordinator for another game.
     */
    private function getGamesDetails(): array
    {
        if (empty($this->games)) {
            return [];
        }

        // Get all registrations for relevant games
        $allRegistrations = \App\Models\PicnicRegistration::where('year', $this->year)
            ->where(function ($query) {
                foreach ($this->games as $game) {
                    $query->orWhereJsonContains('games', $game);
                }
            })
            ->with('user:id,first_name,last_name,email')
            ->orderBy('registered_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // CRITICAL: Determine coordinator assignments GLOBALLY across all games
        // We need to process ALL games, not just current user's games
        $coordinatorAssignments = $this->determineCoordinators($allRegistrations);

        $gamesDetails = [];

        foreach ($this->games as $game) {
            // Filter registrations for this specific game
            $gameRegistrations = $allRegistrations
                ->filter(fn($reg) => in_array($game, $reg->games))
                ->sortBy([
                    ['registered_at', 'asc'],
                    ['id', 'asc']
                ])
                ->values();

            // Get coordinator from global assignments
            $coordinatorId = $coordinatorAssignments[$game] ?? null;
            $coordinator = $coordinatorId
                ? $gameRegistrations->firstWhere('user_id', $coordinatorId)
                : null;

            // Is current user the coordinator?
            $currentUserIsCoordinator = $coordinatorId === $this->user_id;

            // Build "fellow players" list - excluding current user
            $players = $gameRegistrations
                ->filter(fn($reg) => $reg->user_id !== $this->user_id)
                ->map(function ($reg) use ($coordinatorId) {
                    return [
                        'id' => $reg->user->id,
                        'name' => $reg->user->first_name . ' ' . $reg->user->last_name,
                        'email' => $reg->user->email,
                        'is_coordinator' => $reg->user_id === $coordinatorId,
                    ];
                })
                ->values()
                ->toArray();

            $gamesDetails[] = [
                'game' => $game,
                'is_coordinator' => $currentUserIsCoordinator,
                'coordinator' => $coordinator ? [
                    'id' => $coordinator->user->id,
                    'name' => $coordinator->user->first_name . ' ' . $coordinator->user->last_name,
                    'email' => $coordinator->user->email,
                    'registered_at' => $coordinator->registered_at->toISOString(),
                    'is_you' => $coordinator->user_id === $this->user_id,
                ] : null,
                'players' => $players,
                'total_players' => $gameRegistrations->count(),
            ];
        }

        return $gamesDetails;
    }

    /**
     * Determine coordinator assignments across ALL games
     *
     * This is the KEY method that fixes the bug.
     * We process all games globally to ensure one coordinator per user.
     *
     * @param \Illuminate\Support\Collection $allRegistrations
     * @return array [game_name => user_id]
     */
    private function determineCoordinators($allRegistrations): array
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

        $coordinatorAssignments = [];
        $assignedCoordinators = [];

        foreach ($games as $game) {
            // Get all registrations for this game, sorted by time
            $gameRegistrations = $allRegistrations
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
