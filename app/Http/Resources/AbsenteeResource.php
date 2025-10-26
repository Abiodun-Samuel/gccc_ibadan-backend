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
            'user_id' => $this->user_id,

            // User/Member information
            'full_name' => "{$this->user?->first_name} {$this->user?->last_name}",
            'email' => $this->user?->email,
            'avatar' => $this->user?->avatar,
            'initials' => generateInitials($this->user?->first_name, $this->user?->last_name),
            'phone' => $this->user?->phone_number,
            'gender' => $this->user?->gender,

            // Leader information
            'assigned_to_member' => $this->whenLoaded('leader', fn() => [
                'id' => $this->leader->id,
                'full_name' => "{$this->leader->first_name} {$this->leader->last_name}",
                'email' => $this->leader->email,
                'phone' => $this->leader->phone_number,
                'avatar' => $this->leader->avatar,
                'initials' => generateInitials($this->leader->first_name, $this->leader->last_name),
            ]),


            'attendance' => $this->whenLoaded('attendance', fn() => [
                'id' => $this->attendance->id,
                'service_id' => $this->attendance->service_id,
                'status' => $this->attendance->status,
                'attendance_date' => $this->attendance->attendance_date?->format('Y-m-d'),
                'attendance_date_human' => $this->attendance->attendance_date?->format('M d, Y'),
                'service' => $this->attendance->service ?: null,
            ]),

            'service_id' => $this->service_id,
            'attendance_date' => $this->attendance_date?->format('Y-m-d'),
            'attendance_date_human' => $this->attendance_date?->format('M d, Y'),

            // Followup Feedbacks
            'followupFeedbacks' => $this->when(
                $this->relationLoaded('user') && $this->user?->relationLoaded('followupFeedbacks'),
                fn() => FollowupFeedbackResource::collection($this->user->followupFeedbacks)
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
