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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'role' => $this->roles->pluck('name')->first(),
            'gender' => $this->gender,
            'address' => $this->address,
            'community' => $this->community,
            'worker' => $this->worker,
            'unit' => $this->unit,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'date_of_birth' => $this->date_of_birth,
            'date_of_visit' => $this->date_of_visit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
