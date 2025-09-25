<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsherAttendanceResource extends JsonResource
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
            'service_day' => $this->service_day,
            'service_day_desc' => $this->service_day_desc,
            'service_date' => $this->service_date->toDateString(),
            'male' => $this->male,
            'female' => $this->female,
            'children' => $this->children,
            'total_attendance' => $this->total_attendance,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
