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
     * Pro-level optimization:
     * - Single query instead of N queries
     * - Coordinator info visible to all players (except shows separately for coordinator)
     * - Current user excluded from "fellow players" list
     * - Clean separation: you vs others
     */
    private function getGamesDetails(): array
    {
        if (empty($this->games)) {
            return [];
        }

        // Single optimized query to fetch all relevant registrations
        $allRegistrations = \App\Models\PicnicRegistration::where('year', $this->year)
            ->where(function ($query) {
                foreach ($this->games as $game) {
                    $query->orWhereJsonContains('games', $game);
                }
            })
            ->with('user:id,first_name,last_name,email')
            ->orderBy('registered_at', 'asc')
            ->get();

        $gamesDetails = [];

        foreach ($this->games as $game) {
            // Filter registrations for this specific game
            $gameRegistrations = $allRegistrations->filter(fn($reg) => in_array($game, $reg->games));

            // First registration = coordinator (source of truth)
            $coordinator = $gameRegistrations->first();
            $coordinatorId = $coordinator?->user_id;

            // Is current user the coordinator?
            $currentUserIsCoordinator = $coordinatorId === $this->user_id;

            // Build "fellow players" list - excluding current user
            $players = $gameRegistrations
                ->where('user_id', '!=', $this->user_id) // Exclude yourself
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
                'players' => $players, // Other players (not including you)
                'total_players' => $gameRegistrations->count(),
            ];
        }

        return $gamesDetails;
    }
}
