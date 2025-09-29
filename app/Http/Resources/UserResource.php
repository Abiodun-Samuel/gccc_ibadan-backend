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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'gender' => $this->gender,
            'address' => $this->address,
            'community' => $this->community,
            'worker' => $this->worker,
            'status' => $this->status,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'country' => $this->country,
            'city_or_state' => $this->city_or_state,
            'units' => UnitResource::collection($this->whenLoaded('units')),
            'assignedFirstTimers' => $this->assignedFirstTimers,

            'education' => $this->education,
            'field_of_study' => $this->field_of_study,
            'occupation' => $this->occupation,

            'ledUnits' => $this->ledUnits,
            'assistedUnits' => $this->assistedUnits,
            'memberUnits' => $this->memberUnits,

            'roles' => $this->getRoleNames(),
            'permissions' => $this->getPermissionNames(),

            'social_links' => [
                'facebook' => $this->facebook,
                'instagram' => $this->instagram,
                'linkedin' => $this->linkedin,
                'twitter' => $this->twitter,
            ],

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
