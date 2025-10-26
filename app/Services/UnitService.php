<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\Unit;
use App\Models\User;
use DB;

class UnitService
{
    public function __construct(
        private UserRoleService $userRoleService
    ) {
    }

    public function createUnit(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            $memberIds = $data['member_ids'] ?? [];
            if (!empty($data['leader_id'])) {
                $memberIds[] = $data['leader_id'];
            }
            if (!empty($data['assistant_leader_id'])) {
                $memberIds[] = $data['assistant_leader_id'];
            }
            $memberIds = array_unique($memberIds);
            $unit = Unit::create([
                'name' => $data['name'],
                'leader_id' => $data['leader_id'] ?? null,
                'assistant_leader_id' => $data['assistant_leader_id'] ?? null,
            ]);

            if (!empty($memberIds)) {
                $this->syncMembers($unit, $memberIds);
            }
            if (isset($data['leader_id'])) {
                $leader = User::find($data['leader_id']);
                $this->userRoleService->assignUserRoles(
                    $leader,
                    [RoleEnum::LEADER->value]
                );
            }
            if (isset($data['assistant_leader_id'])) {
                $assistant = User::find($data['assistant_leader_id']);
                $this->userRoleService->assignUserRoles(
                    $assistant,
                    [RoleEnum::LEADER->value]
                );
            }

            return $unit->fresh(['leader', 'assistantLeader', 'members']);
        });
    }
    public function updateUnit(Unit $unit, array $data): void
    {
        DB::transaction(function () use ($unit, $data) {
            $previousLeaderId = $unit->leader_id;
            $previousAssistantId = $unit->assistant_leader_id;
            $memberIds = $data['member_ids'] ?? [];

            if (isset($data['name'])) {
                $unit->update(['name' => $data['name']]);
            }

            if (array_key_exists('leader_id', $data)) {
                if ($data['leader_id'] !== null)
                    $memberIds[] = $data['leader_id'];
                $this->updateLeader($unit, $data['leader_id'], $previousLeaderId);
            }

            if (array_key_exists('assistant_leader_id', $data)) {
                if ($data['assistant_leader_id'] !== null)
                    $memberIds[] = $data['assistant_leader_id'];
                $this->updateAssistant($unit, $data['assistant_leader_id'], $previousAssistantId);
            }

            if (!empty($memberIds)) {
                $this->syncMembers($unit, $memberIds);
            } else {
                $unit->members()->detach();
            }
        });
    }
    public function deleteUnit(Unit $unit): bool
    {
        return DB::transaction(function () use ($unit) {
            $leaderId = $unit->leader_id;
            $assistantId = $unit->assistant_leader_id;
            $unit->delete();
            if ($leaderId) {
                $this->removeLeaderRoleIfNotLeading($leaderId);
            }
            if ($assistantId) {
                $this->removeLeaderRoleIfNotLeading($assistantId);
            }
            return true;
        });
    }
    protected function removeLeaderRoleIfNotLeading(int $userId): void
    {
        $user = User::find($userId);

        if (!$user || !$user->hasRole(RoleEnum::LEADER->value)) {
            return;
        }

        $stillLeading = Unit::where('leader_id', $userId)
            ->orWhere('assistant_leader_id', $userId)
            ->exists();

        if (!$stillLeading && $user->hasRole(RoleEnum::LEADER->value)) {
            $this->userRoleService->removeRole(
                $user,
                RoleEnum::LEADER->value
            );
        }
    }

    private function updateLeader(Unit $unit, ?int $newLeaderId, ?int $previousLeaderId): void
    {
        $unit->update(['leader_id' => $newLeaderId]);

        // Assign role to new leader
        if ($newLeaderId) {
            $newLeader = User::findOrFail($newLeaderId);
            $this->userRoleService->assignUserRoles(
                $newLeader,
                [RoleEnum::LEADER->value]
            );
        }

        // Cleanup previous leader if they're no longer leading any unit
        if ($previousLeaderId && $previousLeaderId !== $newLeaderId) {
            $this->removeLeaderRoleIfNotLeading($previousLeaderId);
        }
    }

    private function updateAssistant(Unit $unit, ?int $newAssistantId, ?int $previousAssistantId): void
    {
        $unit->update(['assistant_leader_id' => $newAssistantId]);
        // Assign role to new assistant
        if ($newAssistantId) {
            $newAssistant = User::findOrFail($newAssistantId);
            $this->userRoleService->assignUserRoles(
                $newAssistant,
                [RoleEnum::LEADER->value]
            );
        }

        // Cleanup previous assistant if they're no longer leading any unit
        if ($previousAssistantId && $previousAssistantId !== $newAssistantId) {
            $this->removeLeaderRoleIfNotLeading($previousAssistantId);
        }
    }
    private function syncMembers(Unit $unit, array $memberIds): void
    {
        $unit->members()->sync($memberIds);
    }
}
