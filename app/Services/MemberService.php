<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MemberService
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }
    /**
     * Apply filters for members query.
     */
    private function applyMembersFilters($query, array $filters): void
    {
        // Filter by community
        $query->when(
            !empty($filters['community']),
            fn($q) => $q->where('community', $filters['community'])
        );

        // Filter by specific date(s) of birth
        $query->when(
            !empty($filters['date_of_birth']),
            fn($q) => $q->whereIn(
                'date_of_birth',
                is_array($filters['date_of_birth'])
                    ? $filters['date_of_birth']
                    : [$filters['date_of_birth']]
            )
        );

        $query->when(
            !empty($filters['birth_month']),
            fn($q) => $q->whereMonth('date_of_birth', $filters['birth_month'])
        );
    }

    /**
     * Fetch all members with optional filters.
     */
    public function getAllMembers(array $filters = []): Collection
    {
        $query = User::members();
        $this->applyMembersFilters($query, $filters);
        return $query->get();
    }

    public function getAllUsers(): Collection
    {
        $users = User::get();
        return $users;
    }

    public function getUsersByRole(string $role, array $filters = []): Collection
    {
        $validRoles = [
            'admin',
            'leader',
            'member',
            'firstTimer',
            'pastor',
            'gloryTeam',
            'nonGloryTeam',
            'all',
        ];

        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException("Invalid role: {$role}");
        }

        $query = match ($role) {
            'pastor' => User::pastors(),
            'admin' => User::admins(),
            'leader' => User::leaders(),
            'member' => User::members(),
            'firstTimer' => User::firstTimers(),
            'gloryTeam' => User::gloryTeamMembers()->members(),
            'nonGloryTeam' => User::notGloryTeamMembers()->members(),
            'all' => User::query(),
        };

        $this->applyMembersFilters($query, $filters);

        return $query->with(['units'])->get();
    }

    public function findMember(User $user): User
    {
        if (!$user->hasRole(RoleEnum::MEMBER->value)) {
            throw new NotFoundHttpException('The member you are looking for may have been removed or no longer exists.');
        }
        return $user->load(['assignedTo'])->loadFullProfile();
    }

    /**
     * Create a single member.
     */
    public function createMember(array $data): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'gender' => $data['gender'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['phone_number']),
            'status' => 'active',
        ]);
    }

    /**
     * Bulk member creation with role assignment and error handling.
     */
    public function createMembers(array $membersData): array
    {
        $successful = [];
        $failed = [];

        foreach ($membersData as $index => $memberData) {
            try {
                $member = $this->createMember($memberData);
                $member->assignRole(RoleEnum::MEMBER->value);
                $successful[] = $member;
            } catch (\Exception $e) {
                $failed[] = [
                    'index' => $index + 1,
                    'data' => $memberData,
                    'error' => $this->getUserFriendlyError($e),
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'total' => count($membersData),
            'successful_count' => count($successful),
            'failed_count' => count($failed),
        ];
    }

    /**
     * Update member and handle assignment notifications.
     */
    public function updateMember(User $member, array $validatedData): User
    {
        return DB::transaction(function () use ($member, $validatedData) {
            if (!empty($validatedData['phone_number'])) {
                $validatedData['password'] = Hash::make($validatedData['phone_number']);
            }

            $this->handleMemberAssignment($validatedData);

            $validatedData['assigned_at'] = !empty($validatedData['followup_by_id'])
                ? now()
                : null;

            $member->update($validatedData);

            return $member->fresh(['assignedTo']);
        });
    }

    /**
     * Delete multiple members.
     */
    public function deleteMembers(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            DB::table('unit_user')->whereIn('user_id', $ids)->delete();
            $deletedCount = User::whereIn('id', $ids)->delete();

            return [
                'deleted_count' => $deletedCount,
                'failed_count' => count($ids) - $deletedCount,
                'success' => $deletedCount > 0,
            ];
        });
    }

    /**
     * Delete a single member.
     */
    public function deleteMember(User $member): bool
    {
        return DB::transaction(function () use ($member) {
            $member->units()->detach();
            return $member->delete();
        });
    }

    /**
     * Handle member assignment notifications.
     */
    private function handleMemberAssignment(array &$validatedData): void
    {
        if (empty($validatedData['followup_by_id'])) {
            return;
        }

        $assignedUser = User::select('id', 'first_name', 'email')
            ->find($validatedData['followup_by_id']);

        if (!$assignedUser) {
            return;
        }

        try {
            $this->mailService->sendAssignedMemberEmail([
                [
                    'name' => $assignedUser->first_name,
                    'email' => $assignedUser->email,
                ]
            ]);
        } catch (\Exception $e) {
            // Silent fail (you can log if needed)
        }
    }

    /**
     * Get user-friendly error messages.
     */
    private function getUserFriendlyError(\Exception $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'Duplicate entry')) {
            if (str_contains($message, 'email')) {
                return 'Email already exists';
            }
            if (str_contains($message, 'phone_number')) {
                return 'Phone number already exists';
            }
            return 'Duplicate entry detected';
        }
        return 'An error occurred while creating this member';
    }


    public function assignMembersToFollowupLeaders(array $memberIds, array $followupLeaderIds): array
    {
        if (empty($memberIds)) {
            throw new \InvalidArgumentException('Member IDs array cannot be empty');
        }

        if (empty($followupLeaderIds)) {
            throw new \InvalidArgumentException('Follow-up leader IDs array cannot be empty');
        }

        return DB::transaction(function () use ($memberIds, $followupLeaderIds) {
            // Remove leaders from member list to prevent self-assignment
            $membersToAssign = array_values(array_diff($memberIds, $followupLeaderIds));

            if (empty($membersToAssign)) {
                return [
                    'success' => false,
                    'message' => 'No members to assign after excluding leaders',
                    'total_assigned' => 0,
                    'assignments' => [],
                ];
            }

            $assignments = $this->distributeEvenly($membersToAssign, $followupLeaderIds);
            $this->bulkUpdateAssignments($assignments);
            $this->sendAssignmentNotifications($assignments);

            return [
                'success' => true,
                'total_assigned' => count($membersToAssign),
                'excluded_leaders' => count($memberIds) - count($membersToAssign),
                'assignments' => $assignments,
            ];
        });
    }

    /**
     * Distribute members evenly using round-robin algorithm
     */
    protected function distributeEvenly(array $memberIds, array $followupLeaderIds): array
    {
        $assignments = [];
        $totalLeaders = count($followupLeaderIds);

        foreach ($memberIds as $index => $memberId) {
            $leaderIndex = $index % $totalLeaders;
            $assignments[$memberId] = $followupLeaderIds[$leaderIndex];
        }

        return $assignments;
    }

    /**
     * Bulk update using parameterized query to prevent SQL injection
     */
    protected function bulkUpdateAssignments(array $assignments): void
    {
        if (empty($assignments)) {
            return;
        }

        $memberIds = array_keys($assignments);
        $caseParts = [];
        $bindings = [];

        foreach ($assignments as $memberId => $followupLeaderId) {
            $caseParts[] = "WHEN ? THEN ?";
            $bindings[] = $memberId;
            $bindings[] = $followupLeaderId;
        }

        $caseStatement = "CASE id " . implode(' ', $caseParts) . " END";
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

        DB::update(
            "UPDATE users SET followup_by_id = {$caseStatement}, updated_at = ? WHERE id IN ({$placeholders})",
            array_merge($bindings, [now()], $memberIds)
        );
    }

    /**
     * Send email notifications to all assigned leaders at once
     */
    protected function sendAssignmentNotifications(array $assignments): void
    {
        if (empty($assignments)) {
            return;
        }

        // Group members by their assigned leader
        $leaderAssignments = [];
        foreach ($assignments as $memberId => $leaderId) {
            $leaderAssignments[$leaderId][] = $memberId;
        }

        // Fetch all leaders and members in 2 optimized queries
        $leaders = User::whereIn('id', array_keys($leaderAssignments))
            ->select('id', 'first_name', 'last_name', 'email')
            ->get()
            ->keyBy('id');

        $members = User::whereIn('id', array_keys($assignments))
            ->select('id', 'first_name', 'last_name', 'email', 'phone_number')
            ->get()
            ->keyBy('id');

        // Build recipients array for bulk email
        $recipients = $leaders->filter()
            ->map(fn($leader) => [
                'name' => trim($leader->first_name . ' ' . ($leader->last_name ?? '')),
                'email' => $leader->email,
            ])
            ->values()
            ->toArray();

        // Send one email to all leaders at once
        if (!empty($recipients)) {
            try {
                $this->mailService->sendNewMembersAssignedMail($recipients);
            } catch (\Exception $e) {
                throw $e; // Re-throw to rollback transaction
            }
        }
    }
    public function updateGloryTeamMembers(): int
    {
        try {
            $updated = User::query()
                ->where(function ($query) {
                    // Users in any unit
                    $query->whereHas('units')
                        // OR users with admin, pastor, or leader roles
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->whereIn('name', ['admin', 'pastor', 'leader']);
                        });
                })
                ->where('is_glory_team_member', false)
                ->update(['is_glory_team_member' => true]);

            return $updated;
        } catch (\Exception $e) {
            throw new \Exception('Failed to update glory team members: ' . $e->getMessage());
        }
        // try {
        //     $updated = DB::table('users')
        //         ->whereExists(function ($query) {
        //             $query->select(DB::raw(1))
        //                 ->from('unit_user')
        //                 ->whereColumn('unit_user.user_id', 'users.id');
        //         })
        //         ->where('is_glory_team_member', false)
        //         ->update(['is_glory_team_member' => true]);
        //     return $updated;
        // } catch (\Exception $e) {

        //     throw new \Exception('Failed to update glory team members: ' . $e->getMessage());
        // }
    }
}
