<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FirstTimerDeletionService
{
    /** A first timer holding any of these roles has an account the church still uses: never deleted here. */
    public const PROTECTED_ROLES = [
        RoleEnum::MEMBER->value,
        RoleEnum::LEADER->value,
        RoleEnum::PASTOR->value,
        RoleEnum::ADMIN->value,
    ];

    public function __construct(private readonly AccountDeletionService $accounts) {}

    /**
     * Permanently deletes the given first timers, all or nothing.
     *
     * Each one goes through Eloquent so Spatie removes their role links; their login tokens go
     * first. The database's cascades then remove their attendance, follow-up notes, messages,
     * forms, event registrations and rewards, and clear anyone's link to them as a follow-up.
     * Skipped: ids that aren't first timers (or no longer exist); people who are now members; and
     * people who serve in a unit or have written follow-up notes, since deleting them would also
     * delete records about other people. Photos are removed from Cloudinary only after the delete
     * is committed, only from the church's own account, and only when nobody else uses them.
     *
     * @param  int[]  $ids
     * @return array{deleted: array<int, array{id: int, full_name: string}>, skipped: array<int, array{id: int, full_name: ?string, reason: string}>}
     */
    public function deleteMany(array $ids, User $by): array
    {
        $photos = [];

        $result = DB::transaction(function () use ($ids, &$photos) {
            $firstTimers = User::firstTimers()
                ->with('roles')
                ->withCount(['units', 'createdFollowUpFeedbacks', 'assignedAbsentees'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $deleted = [];
            $skipped = [];

            foreach ($ids as $id) {
                $firstTimer = $firstTimers->get($id);

                if (!$firstTimer) {
                    $skipped[] = ['id' => $id, 'full_name' => null, 'reason' => 'not_found'];
                    continue;
                }

                $name = trim("{$firstTimer->first_name} {$firstTimer->last_name}");

                if ($firstTimer->roles->pluck('name')->intersect(self::PROTECTED_ROLES)->isNotEmpty()) {
                    $skipped[] = ['id' => $id, 'full_name' => $name, 'reason' => 'member'];
                    continue;
                }

                if ($firstTimer->units_count > 0
                    || $firstTimer->created_follow_up_feedbacks_count > 0
                    || $firstTimer->assigned_absentees_count > 0) {
                    $skipped[] = ['id' => $id, 'full_name' => $name, 'reason' => 'in_use'];
                    continue;
                }

                $photos[$id] = $this->accounts->deleteAccount($firstTimer);
                $deleted[] = ['id' => $id, 'full_name' => $name];
            }

            return ['deleted' => $deleted, 'skipped' => $skipped];
        });

        $this->accounts->removePhotos($photos, 'First timer');

        Log::info('First timers deleted', [
            'by'      => $by->id,
            'deleted' => array_column($result['deleted'], 'id'),
            'skipped' => array_column($result['skipped'], 'id'),
        ]);

        return $result;
    }

    /** What happened, in a sentence or three, e.g. "Deleted 3 first timers. Skipped 1 who is now a member: Ada Obi." */
    public function message(array $result): string
    {
        $deleted = count($result['deleted']);
        $members = array_values(array_filter($result['skipped'], fn($row) => $row['reason'] === 'member'));
        $inUse = array_values(array_filter($result['skipped'], fn($row) => $row['reason'] === 'in_use'));
        $missing = count($result['skipped']) - count($members) - count($inUse);

        $parts = [$deleted === 0
            ? 'No first timers were deleted.'
            : 'Deleted ' . $deleted . ' first ' . ($deleted === 1 ? 'timer' : 'timers') . '.'];

        if ($members) {
            $parts[] = 'Skipped ' . count($members) . ' ' . (count($members) === 1 ? 'who is now a member' : 'who are now members')
                . ': ' . implode(', ', array_column($members, 'full_name')) . '.';
        }

        if ($inUse) {
            $parts[] = 'Skipped ' . count($inUse) . ' '
                . (count($inUse) === 1 ? 'who serves in a unit or has written follow-up notes' : 'who serve in a unit or have written follow-up notes')
                . ': ' . implode(', ', array_column($inUse, 'full_name')) . '.';
        }

        if ($missing > 0) {
            $parts[] = $missing . ' could not be found.';
        }

        return implode(' ', $parts);
    }
}
