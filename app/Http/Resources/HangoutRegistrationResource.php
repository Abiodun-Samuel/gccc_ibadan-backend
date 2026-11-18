<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HangoutRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'full_name'       => $this->full_name,
            'gender'          => $this->gender,
            'email'           => $this->email,
            'phone_number'    => $this->phone_number,
            'whatsapp_number' => $this->whatsapp_number,
            'game'            => $this->game,
            'menu'            => $this->menu,
            'support_amount'  => $this->support_amount !== null ? (float) $this->support_amount : null,
            'children'        => $this->whenLoaded('children', fn() => $this->children
                ->map(fn($child) => $child->only(['full_name', 'gender', 'game', 'menu']))
                ->values()
                ->all()),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
