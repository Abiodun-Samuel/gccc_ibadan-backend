<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'avatar' => $this->avatar,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'gender' => $this->gender,
            'address' => $this->address,
            'community' => $this->community,
            'worker' => $this->worker,
            'status' => $this->status,
            'date_of_birth' => $this->date_of_birth,
            'country' => $this->country,
            'city_or_state' => $this->city_or_state,
            'units' => UnitResource::collection($this->whenLoaded('units')),
            'assignedFirstTimers' => $this->assignedFirstTimers,
            'role' => $this->roles->pluck('name')->first(),
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'linkedin' => $this->linkedin,
            'twitter' => $this->twitter,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
