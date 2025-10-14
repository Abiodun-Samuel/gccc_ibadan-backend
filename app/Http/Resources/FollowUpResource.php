<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_timer_id' => $this->first_timer_id,
            'first_timer' => $this->whenLoaded('firstTimer') ? [
                'id' => $this->firstTimer?->id,
                'full_name' => "{$this->firstTimer?->first_name} {$this->firstTimer?->last_name}",
                'initials' => generateInitials($this->firstTimer?->first_name, $this->firstTimer?->last_name),
                'email' => $this->firstTimer?->email,
                'phone' => $this->firstTimer?->phone_number,
                'gender' => $this->firstTimer?->gender,
                'avatar' => $this->firstTimer?->avatar,
            ] : null,
            'user' => $this->whenLoaded('user') ? [
                'id' => $this->user?->id,
                'full_name' => "{$this->user?->first_name} {$this->user?->last_name}",
                'initials' => generateInitials($this->user?->first_name, $this->user?->last_name),
                'email' => $this->user?->email,
                'avatar' => $this->user?->avatar,
                'phone' => $this->user?->phone_number,
                'gender' => $this->user?->gender,
            ] : null,
            'type' => $this->type,
            'note' => $this->note,
            'service_date' => $this->service_date,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
