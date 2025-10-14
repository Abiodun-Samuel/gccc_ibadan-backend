<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsenteeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => "{$this->user?->first_name} {$this->user?->last_name}",
            'email' => $this->user?->email,
            'phone' => $this->user?->phone_number,
            'gender' => $this->user?->gender,
            'attendance' => $this->attendance ? [
                'id' => $this->attendance->id,
                'service_id' => $this->attendance->service_id,
                'status' => $this->attendance->status,
                'attendance_date' => $this->attendance->attendance_date,
                'service' => $this->attendance->service ?: null,
            ] : null,
        ];
    }
}
