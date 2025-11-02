<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowupFeedbackResource extends JsonResource
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

            // Subject information (the user this feedback is about)
            'subject' => $this->whenLoaded('user', fn() => $this->formatSubject()),

            'user_id' => $this->user_id,

            // Creator information (who wrote the feedback)
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'full_name' => "{$this->createdBy->first_name} {$this->createdBy->last_name}",
                'initials' => generateInitials($this->createdBy->first_name, $this->createdBy->last_name),
                'email' => $this->createdBy->email,
                'avatar' => $this->createdBy->avatar,
                'phone' => $this->createdBy->phone_number,
                'gender' => $this->createdBy->gender,
            ]),

            // Feedback details
            'type' => $this->type,
            'note' => $this->note,
            'service_date' => $this->service_date?->format('Y-m-d'),
            'service_date_human' => $this->service_date?->format('M d, Y'),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at_human' => $this->updated_at->diffForHumans(),
        ];
    }

    /**
     * Format the subject (user) data
     */
    private function formatSubject(): ?array
    {
        if (!$this->user) {
            return null;
        }

        $user = $this->user;

        return [
            'id' => $user->id,
            'full_name' => "{$user->first_name} {$user->last_name}",
            'initials' => generateInitials($user->first_name, $user->last_name),
            'email' => $user->email,
            'phone' => $user->phone_number,
            'gender' => $user->gender,
            'avatar' => $user->avatar ?? null,
            'type' => 'member', // since FirstTimer no longer exists
            'role' => $user->role ?? null,
            'community' => $user->community ?? null,
        ];
    }
}
