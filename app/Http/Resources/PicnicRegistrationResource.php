<?php

namespace App\Http\Resources;

use App\Models\PicnicRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PicnicRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'games' => $this->games,
            'support_amount' => $this->support_amount ? (float) $this->support_amount : null,
            'registered_at' => $this->registered_at->toISOString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
                'email' => $this->user->email,
                'phone_number' => $this->user->phone_number,
            ],
            'players_by_game' => $this->getPlayersByGame(),
        ];
    }

    private function getPlayersByGame(): array
    {
        $playersByGame = [];

        foreach ($this->games as $game) {
            $players = PicnicRegistration::with('user:id,first_name,last_name,email,phone_number')
                ->where('year', $this->year)
                ->where('id', '!=', $this->id)
                ->whereJsonContains('games', $game)
                ->get()
                ->pluck('user')
                ->map(fn($user) => [
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                ])
                ->toArray();

            if (!empty($players)) {
                $playersByGame[$game] = $players;
            }
        }

        return $playersByGame;
    }
}
