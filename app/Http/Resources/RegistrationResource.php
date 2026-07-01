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
            'needs_accommodation'       => $this->needs_accommodation,
            'travelling_with_us'        => $this->travelling_with_us,
            'needs_transport_fare_help' => $this->needs_transport_fare_help,
            'travel_date'               => $this->travel_date?->toDateString(),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
