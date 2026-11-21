<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberDeletionService
{
    /** People with these roles are never deleted from the members table. */
    public const PROTECTED_ROLES = [
        RoleEnum::ADMIN->value,
        RoleEnum::PASTOR->value,
    ];

    public function __construct(private readonly AccountDeletionService $accounts) {}

    /**
     * Permanently deletes the given people, all or nothing, in the order asked.
     *
     * Skipped: ids that don't exist (not_found); the admin asking (self); admins and pastors
     * (protected); and anyone whose delete would take records about other people with it or leave
     * them without their person (in_use): follow-up notes they wrote, absentee follow-up they lead,
     * people assigned to them, or a unit they lead or assist. Plain unit membership doesn't count.
     *
     * @param  int[]  $ids
     * @return array{deleted: array<int, array{id: int, full_name: string}>, skipped: array<int, array{id: int, full_name: ?string, reason: string}>}
     */
    public function deleteMany(array $ids, User $by): array
    {
        $photos = [];

        $result = DB::transaction(function () use ($ids, $by, &$photos) {
            $people = User::query()
                ->with('roles')
                ->withCount(['createdFollowUpFeedbacks', 'assignedAbsentees', 'assignedUsers'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $unitLeaders = $this->unitLeaderIds($ids);
            $deleted = [];
            $skipped = [];

            foreach ($ids as $id) {
                $person = $people->get($id);

                if (!$person) {
                    $skipped[] = ['id' => $id, 'full_name' => null, 'reason' => 'not_found'];
                    continue;
                }

                $name = trim("{$person->first_name} {$person->last_name}");

                if ($person->id === $by->id) {
                    $skipped[] = ['id' => $id, 'full_name' => $name, 'reason' => 'self'];
                    continue;
                }

                if ($person->roles->pluck('name')->intersect(self::PROTECTED_ROLES)->isNotEmpty()) {
                    $skipped[] = ['id' => $id, 'full_name' => $name, 'reason' => 'protected'];
                    continue;
                }

                if ($person->created_follow_up_feedbacks_count > 0
                    || $person->assigned_absentees_count > 0
                    || $person->assigned_users_count > 0
                    || in_array($person->id, $unitLeaders, true)) {
                    $skipped[] = ['id' => $id, 'full_name' => $name, 'reason' => 'in_use'];
                    continue;
                }

                $photos[$id] = $this->accounts->deleteAccount($person);
                $deleted[] = ['id' => $id, 'full_name' => $name];
            }

            return ['deleted' => $deleted, 'skipped' => $skipped];
        });

        $this->accounts->removePhotos($photos, 'Member');

        Log::info('Members deleted', [
            'by'      => $by->id,
            'deleted' => array_column($result['deleted'], 'id'),
            'skipped' => array_column($result['skipped'], 'id'),
        ]);

        return $result;
    }

    /** e.g. "Deleted 3 members. Skipped your own account. Skipped 1 admin or pastor: Ada Obi." */
    public function message(array $result): string
    {
        $deleted = count($result['deleted']);
        $byReason = fn(string $reason) => array_values(array_filter($result['skipped'], fn($row) => $row['reason'] === $reason));
        $protected = $byReason('protected');
        $inUse = $byReason('in_use');
        $names = fn(array $rows) => implode(', ', array_column($rows, 'full_name'));

        $parts = [$deleted === 0
            ? 'No members were deleted.'
            : 'Deleted ' . $deleted . ' ' . ($deleted === 1 ? 'member' : 'members') . '.'];

        if ($byReason('self')) {
            $parts[] = 'Skipped your own account.';
        }

        if ($protected) {
            $parts[] = 'Skipped ' . count($protected) . ' '
                . (count($protected) === 1 ? 'admin or pastor' : 'admins or pastors') . ': ' . $names($protected) . '.';
        }

        if ($inUse) {
            $parts[] = 'Skipped ' . count($inUse) . ' '
                . (count($inUse) === 1 ? 'who still has follow-up work or leads a unit' : 'who still have follow-up work or lead a unit')
                . ': ' . $names($inUse) . '.';
        }

        $missing = count($byReason('not_found'));
        if ($missing > 0) {
            $parts[] = $missing . ' could not be found.';
        }

        return implode(' ', $parts);
    }

    /** Of the given ids, those who lead or assist a unit. */
    private function unitLeaderIds(array $ids): array
    {
        return Unit::query()
            ->where(fn($q) => $q->whereIn('leader_id', $ids)
                ->orWhereIn('assistant_leader_id', $ids)
                ->orWhereIn('assistant_leader_id_2', $ids))
            ->get(['leader_id', 'assistant_leader_id', 'assistant_leader_id_2'])
            ->flatMap(fn($unit) => [$unit->leader_id, $unit->assistant_leader_id, $unit->assistant_leader_id_2])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
