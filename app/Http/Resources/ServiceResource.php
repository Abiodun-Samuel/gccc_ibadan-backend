<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'start_time' => $this->start_time,
            'day_of_week' => $this->is_recurring ? $this->day_of_week : null,
            'service_date' => $this->is_recurring ? null : $this->service_date,
            'is_recurring' => (bool) $this->is_recurring,
        ];
    }
}
