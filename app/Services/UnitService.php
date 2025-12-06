<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function __construct(
        private UserRoleService $userRoleService
    ) {}

    public function createUnit(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            $unit = Unit::create([
                'name' => $data['name'],
                'leader_id' => $data['leader_id'] ?? null,
                'assistant_leader_id' => $data['assistant_leader_id'] ?? null,
                'assistant_leader_id_2' => $data['assistant_leader_id_2'] ?? null,
            ]);

            $memberIds = $this->collectMemberIds($data);

            if (!empty($memberIds)) {
                $unit->members()->sync($memberIds);
            }

            $this->assignLeadershipRoles($data);

            return $unit->fresh(['leader', 'assistantLeader', 'assistantLeader2', 'members']);
        });
    }

    public function updateUnit(Unit $unit, array $data): void
    {
        DB::transaction(function () use ($unit, $data) {
            $previousLeaders = [
                'leader_id' => $unit->leader_id,
                'assistant_leader_id' => $unit->assistant_leader_id,
                'assistant_leader_id_2' => $unit->assistant_leader_id_2,
            ];

            if (isset($data['name'])) {
                $unit->update(['name' => $data['name']]);
            }

            $this->updateLeadershipPositions($unit, $data, $previousLeaders);

            $memberIds = $this->collectMemberIds($data);
            $unit->members()->sync($memberIds);
        });
    }

    public function deleteUnit(Unit $unit): bool
    {
        return DB::transaction(function () use ($unit) {
            $leaderIds = array_filter([
                $unit->leader_id,
                $unit->assistant_leader_id,
                $unit->assistant_leader_id_2,
            ]);

            $unit->delete();

            foreach ($leaderIds as $leaderId) {
                $this->removeLeaderRoleIfNotLeading($leaderId);
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
            ->orWhere('assistant_leader_id_2', $userId)
            ->exists();

        if (!$stillLeading) {
            $this->userRoleService->removeRole($user, RoleEnum::LEADER->value);
        }
    }

    private function collectMemberIds(array $data): array
    {
        $memberIds = $data['member_ids'] ?? [];

        $leadershipIds = array_filter([
            $data['leader_id'] ?? null,
            $data['assistant_leader_id'] ?? null,
            $data['assistant_leader_id_2'] ?? null,
        ]);

        return array_unique(array_merge($memberIds, $leadershipIds));
    }

    private function assignLeadershipRoles(array $data): void
    {
        $leadershipMapping = [
            'leader_id',
            'assistant_leader_id',
            'assistant_leader_id_2',
        ];

        foreach ($leadershipMapping as $key) {
            if (isset($data[$key])) {
                $user = User::find($data[$key]);
                if ($user) {
                    $this->userRoleService->assignUserRoles($user, [RoleEnum::LEADER->value]);
                }
            }
        }
    }

    private function updateLeadershipPositions(Unit $unit, array $data, array $previousLeaders): void
    {
        $leadershipFields = [
            'leader_id',
            'assistant_leader_id',
            'assistant_leader_id_2',
        ];

        foreach ($leadershipFields as $field) {
            if (array_key_exists($field, $data)) {
                $this->updateLeadershipField(
                    $unit,
                    $field,
                    $data[$field],
                    $previousLeaders[$field]
                );
            }
        }
    }

    private function updateLeadershipField(
        Unit $unit,
        string $field,
        ?int $newLeaderId,
        ?int $previousLeaderId
    ): void {
        $unit->update([$field => $newLeaderId]);

        if ($newLeaderId) {
            $newLeader = User::findOrFail($newLeaderId);
            $this->userRoleService->assignUserRoles($newLeader, [RoleEnum::LEADER->value]);
        }

        if ($previousLeaderId && $previousLeaderId !== $newLeaderId) {
            $this->removeLeaderRoleIfNotLeading($previousLeaderId);
        }
    }
}
