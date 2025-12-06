<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MemberService
{
    private const CACHE_KEY_PREFIX = 'members_list';
    private const CACHE_TTL = 5; // minutes

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
     * Fetch all members with optional filters and caching.
     */
    public function getAllMembers(array $filters = []): Collection
    {
        $cacheKey = $this->generateCacheKey(self::CACHE_KEY_PREFIX, $filters);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL),
            function () use ($filters) {
                $query = User::members();
                $this->applyMembersFilters($query, $filters);
                return $query->get();
            }
        );
    }

    /**
     * Get users by role with caching.
     */
    public function getUsersByRole(string $role): Collection
    {
        $validRoles = ['admin', 'leader', 'member', 'firstTimer'];

        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException("Invalid role: {$role}");
        }

        $cacheKey = self::CACHE_KEY_PREFIX . "_role_{$role}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL),
            fn() => match ($role) {
                'admin' => User::admins()->get(),
                'leader' => User::leaders()->get(),
                'member' => User::members()->get(),
                'firstTimer' => User::firstTimers()->get(),
            }
        );
    }

    /**
     * Find a single member with relationships.
     */
    public function findMember(User $user): User
    {
        if (!$user->hasRole(RoleEnum::MEMBER->value)) {
            throw new NotFoundHttpException('The member you’re looking for may have been removed or no longer exists.');
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

        $this->clearCache();

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
            $this->clearCache();

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

            $this->clearCache();

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
            $deleted = $member->delete();
            $this->clearCache();
            return $deleted;
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
     * Clear member-related caches.
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX);
        foreach (['admin', 'leader', 'member', 'firstTimer'] as $role) {
            Cache::forget(self::CACHE_KEY_PREFIX . "_role_{$role}");
        }
    }

    /**
     * Generate a consistent cache key with filters.
     */
    private function generateCacheKey(string $baseKey, array $filters = []): string
    {
        if (empty($filters)) {
            return $baseKey;
        }
        $filterKey = md5(json_encode($filters));
        return "{$baseKey}_{$filterKey}";
    }

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
}
