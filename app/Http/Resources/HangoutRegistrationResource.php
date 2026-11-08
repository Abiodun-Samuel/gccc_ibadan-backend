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
            'email'           => $this->email,
            'phone_number'    => $this->phone_number,
            'whatsapp_number' => $this->whatsapp_number,
            'game'            => $this->game,
            'support_amount'  => $this->support_amount !== null ? (float) $this->support_amount : null,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
