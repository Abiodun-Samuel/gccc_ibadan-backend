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

            // Subject information (who the feedback is about)
            'subject' => $this->whenLoaded('followupable', fn() => $this->formatSubject()),

            // Metadata about the subject
            'subject_type' => $this->followupable_type ? class_basename($this->followupable_type) : null,
            'subject_id' => $this->followupable_id,

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

    private function formatSubject(): ?array
    {
        if (!$this->followupable) {
            return null;
        }

        $subject = $this->followupable;

        $baseData = [
            'id' => $subject->id,
            'full_name' => "{$subject->first_name} {$subject->last_name}",
            'initials' => generateInitials($subject->first_name, $subject->last_name),
            'email' => $subject->email,
            'phone' => $subject->phone_number,
            'gender' => $subject->gender,
            'avatar' => $subject->avatar ?? null,
        ];

        if ($this->isForFirstTimer()) {
            $baseData['type'] = 'first_timer';
            $baseData['date_of_visit'] = $subject->date_of_visit?->format('Y-m-d');
            $baseData['status'] = $subject->status;
            $baseData['follow_up_status'] = $subject->followUpStatus?->name ?? null;
        } else {
            $baseData['type'] = 'member';
            $baseData['role'] = $subject->role;
            $baseData['community'] = $subject->community;
        }

        return $baseData;
    }
}
