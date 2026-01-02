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
        $isAssistantLeader = $this->assistant_leader_id === $user?->id || $this->assistant_leader_id_2 === $user?->id;

        return [
            'id' => $this->id,
            'name' => $this->name,

            'leader_id' => $this->leader_id,
            'assistant_leader_id' => $this->assistant_leader_id,
            'assistant_leader_id_2' => $this->assistant_leader_id_2,

            'leader' => $this->whenLoaded('leader', fn() => $this->formatLeaderData($this->leader)),
            'assistantLeader' => $this->whenLoaded('assistantLeader', fn() => $this->formatLeaderData($this->assistantLeader)),
            'assistantLeader2' => $this->whenLoaded('assistantLeader2', fn() => $this->formatLeaderData($this->assistantLeader2)),

            'members' => $this->whenLoaded('members', fn() => $this->members->map(function ($member) {
                return $this->formatLeaderData($member);
            })),
            'members_count' => $this->getMembersCount(),

            'isLeader' => $isLeader,
            'isAssistantLeader' => $isAssistantLeader,
            'isMember' => $this->checkIsMember($user, $isLeader, $isAssistantLeader),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Format leader data
     */
    private function formatLeaderData($leader): ?array
    {
        if (!$leader) {
            return null;
        }

        return [
            'id' => $leader->id,
            'first_name' => $leader->first_name,
            'last_name' => $leader->last_name,
            'full_name' => "{$leader->first_name} {$leader->last_name}",
            'initials' => generateInitials($leader->first_name, $leader->last_name),
            'email' => $leader->email,
            'phone' => $leader->phone_number,
            'gender' => $leader->gender,
            'avatar' => $leader?->avatar,
        ];
    }

    /**
     * Get members count safely
     */
    private function getMembersCount(): int
    {
        if (isset($this->members_count)) {
            return $this->members_count;
        }

        if ($this->relationLoaded('members')) {
            return $this->members->count();
        }

        return 0;
    }

    /**
     * Check if user is a member
     */
    private function checkIsMember($user, bool $isLeader, bool $isAssistantLeader): bool
    {
        // If already a leader or assistant leader, they're a member
        if ($isLeader || $isAssistantLeader) {
            return true;
        }

        // Only check members collection if it's loaded
        if ($this->relationLoaded('members') && $user) {
            return $this->members->contains($user->id);
        }

        return false;
    }
}
