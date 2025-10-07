<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isLeader = $this->leader_id === $user?->id;
        $isAssistantLeader = $this->assistant_leader_id === $user?->id;

        return [
            'id' => $this->id,
            'name' => $this->name,

            'leader_id' => $this->leader_id,
            'assistant_leader_id' => $this->assistant_leader_id,

            'leader' => $this->whenLoaded('leader') ? [
                'id' => $this->leader?->id,
                'name' => $this->leader?->full_name,
                'initials' => $this->leader?->initials,
                'email' => $this->leader?->email,
                'phone' => $this->leader?->phone_number,
                'gender' => $this->leader?->gender,
            ] : null,
            'assistantLeader' => $this->whenLoaded('assistantLeader') ? [
                'id' => $this->assistantLeader?->id,
                'name' => $this->assistantLeader?->full_name,
                'initials' => $this->assistantLeader?->initials,
                'email' => $this->assistantLeader?->email,
                'phone' => $this->assistantLeader?->phone_number,
                'gender' => $this->assistantLeader?->gender,
            ] : null,
            'members' => $this->whenLoaded('members'),
            'members_count' => $this->members_count ?? 0,

            'isLeader' => $isLeader,
            'isAssistantLeader' => $isAssistantLeader,
            'isMember' =>
                $isLeader ||
                $isAssistantLeader ||
                $this->members->contains($user?->id),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
