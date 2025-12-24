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
            // Enriched games with players and coordinator status
            'games_details' => $this->getGamesDetails(),
        ];
    }

    /**
     * Get enriched game details including players and coordinator status
     * Optimized: Only 2 queries instead of N queries per game
     */
    private function getGamesDetails(): array
    {
        if (empty($this->games)) {
            return [];
        }

        $allRegistrations = \App\Models\PicnicRegistration::where('year', $this->year)
            ->where(function ($query) {
                foreach ($this->games as $game) {
                    $query->orWhereJsonContains('games', $game);
                }
            })
            ->with('user:id,first_name,last_name,email')
            ->orderBy('registered_at', 'asc')
            ->get();

        // Build game details
        $gamesDetails = [];

        foreach ($this->games as $game) {
            // Filter registrations for this specific game
            $gameRegistrations = $allRegistrations->filter(fn($reg) => in_array($game, $reg->games));

            // First registration = coordinator
            $firstReg = $gameRegistrations->first();
            $isCoordinator = $firstReg && $firstReg->user_id === $this->user_id;

            // Get other players (exclude current user)
            $players = $gameRegistrations
                ->where('user_id', '!=', $this->user_id)
                ->map(fn($reg) => [
                    'id' => $reg->user->id,
                    'name' => $reg->user->first_name . ' ' . $reg->user->last_name,
                    'email' => $reg->user->email,
                ])
                ->values()
                ->toArray();

            $gamesDetails[] = [
                'game' => $game,
                'is_coordinator' => $isCoordinator,
                'players' => $players,
                'total_players' => $gameRegistrations->count(),
            ];
        }

        return $gamesDetails;
    }
}
