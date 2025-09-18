<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'user_id' => $this->user->id,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'role' => $this->user->roles->pluck('name')->first(),
            'phone_number' => $this->user->phone_number,
            'service_name' => $this->service?->name,
            'attendance_date' => $this->attendance_date,
            'service_day_of_week' => $this->service?->day_of_week,
            'status' => $this->status,
            'service_description' => $this->service?->description,
            'mode' => $this->mode,
            'service_id' => $this->service?->id,
            'service_start_time' => $this->service?->start_time,
            'created_at' => $this->created_at,
        ];
    }
}
