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
            'attendance_date' => $this->attendance_date,
            'status' => $this->status,
            'mode' => $this->mode,

            'user' => $this->whenLoaded('user') ? [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'gender' => $this->user->gender,
                'phone_number' => $this->user->phone_number,
            ] : null,

            'service' => $this->whenLoaded('service') ? [
                'id' => $this->service->id,
                'name' => $this->service?->name,
                'day_of_week' => $this->service?->day_of_week,
                'description' => $this->service?->description,
                'start_time' => $this->service?->start_time,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
