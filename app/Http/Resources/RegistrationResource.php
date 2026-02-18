<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'event_id'        => $this->event_id,
            'title'           => $this->title,
            'full_name'       => $this->full_name,
            'phone_number'    => $this->phone_number,
            'whatsapp_number' => $this->whatsapp_number,
            'email'           => $this->email,
            'attending'       => $this->attending,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
