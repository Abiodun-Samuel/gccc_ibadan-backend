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
