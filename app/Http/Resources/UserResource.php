<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'avatar' => $this->avatar,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'initials' => generateInitials($this->first_name, $this->last_name),
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'gender' => $this->gender,
            'address' => $this->address,
            'community' => $this->community,
            'worker' => $this->getWorkerStatus(),
            'status' => $this->status,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'country' => $this->country,
            'city_or_state' => $this->city_or_state,
            'attendance_badge' => $this->attendance_badge,
            'last_badge_month' => $this->last_badge_month,
            'last_badge_year' => $this->last_badge_year,

            'units' => UnitResource::collection($this->whenLoaded('units')),

            'followupFeedbacks' => $this->when(
                $this?->relationLoaded('followupFeedbacks'),
                fn() => FollowupFeedbackResource::collection($this->followupFeedbacks)
            ),

            'assigned_to_member' => $this->whenLoaded('assignedTo', fn() => [
                'id' => $this->assignedTo->id,
                'full_name' => $this->getAssignedToFullName(),
                'avatar' => $this->assignedTo->avatar,
                'email' => $this->assignedTo->email,
                'gender' => $this->assignedTo->gender,
            ]),

            'roles' => $this->whenLoaded('roles', fn() => $this->getRoleNames()),

            'education' => $this->education,
            'field_of_study' => $this->field_of_study,
            'occupation' => $this->occupation,

            'social_links' => $this->getSocialLinks(),

            'profile_completed' => $this->isProfileCompleted(),
            'completion_percent' => $this->profile_completion_percent,

            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    private function getAssignedToFullName(): string
    {
        return "{$this->assignedTo->first_name} {$this->assignedTo->last_name}";
    }

    private function getWorkerStatus(): string
    {
        // Check if units relationship is loaded and has records
        if ($this->relationLoaded('units') && $this->units->isNotEmpty()) {
            return 'Yes';
        }
        return $this->worker ?? 'No';
    }

    private function getSocialLinks(): array
    {
        return [
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'linkedin' => $this->linkedin,
            'twitter' => $this->twitter,
        ];
    }
}
