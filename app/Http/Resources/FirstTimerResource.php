<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirstTimerResource extends JsonResource
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
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'gender' => $this->gender,
            'located_in_ibadan' => (bool) $this->located_in_ibadan,
            'interest' => $this->interest,
            'born_again' => $this->born_again,
            'whatsapp_interest' => (bool) $this->whatsapp_interest,
            'address' => $this->address,
            'status' => $this->status,
            'date_of_visit' => optional($this->date_of_visit)->format('Y-m-d'),
            'date_of_birth' => optional($this->date_of_birth)->format('Y-m-d'),
            'occupation' => $this->occupation,
            'invited_by' => $this->invited_by,
            'service_experience' => $this->service_experience,
            'prayer_point' => $this->prayer_point,
            'friend_family' => $this->friend_family,
            'how_did_you_learn' => $this->how_did_you_learn,
            'is_student' => $this->is_student,
            'notes' => $this->notes,
            'week_ending' => optional($this->week_ending)->format('Y-m-d'),
            'visitation_report' => $this->visitation_report,
            'pastorate_call' => $this->pastorate_call,
            'assigned_at' => optional($this->assigned_at)->toDateTimeString(),

            // Relationships
            'follow_up_status' => $this->whenLoaded('followUpStatus', fn() => [
                'id' => $this->followUpStatus->id,
                'title' => $this->followUpStatus->title,
                'color' => $this->followUpStatus->color,
            ]),

            'assigned_to_member' => $this->whenLoaded('assignedTo', fn() => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->first_name . ' ' . $this->assignedTo->last_name,
                'email' => $this->assignedTo->email,
                'gender' => $this->assignedTo->gender,
            ]),

            'followupNotes' => $this->whenLoaded('followupNotes'),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
