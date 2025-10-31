<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class MemberService
{
    private const CACHE_KEY = 'members_list';
    private const CACHE_TTL = 3600;
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }
    public function getAllMembers(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => User::members()->get()
        );
    }
    public function getUsersByRole(string $role): Collection
    {
        $cacheKey = self::CACHE_KEY . "_role_{$role}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => match ($role) {
                'admin' => User::admins()->get(),
                'leader' => User::leaders()->get(),
                'member' => User::members()->get(),
                'firstTimer' => User::firstTimers()->get(),
                default => throw new InvalidArgumentException("Invalid role: {$role}"),
            }
        );
    }
    public function findMember(User $user): User
    {
        return $user->load(['assignedTo'])->loadFullProfile();
    }
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
    public function createMembers(array $membersData): array
    {
        $successful = [];
        $failed = [];
        $total = count($membersData);

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
            'total' => $total,
            'successful_count' => count($successful),
            'failed_count' => count($failed),
        ];
    }
    public function updateMember(User $member, array $validatedData): User
    {
        DB::beginTransaction();
        try {
            if (!empty($validatedData['phone_number'])) {
                $validatedData['password'] = Hash::make($validatedData['phone_number']);
            }
            $this->handleMemberAssignment($validatedData);
            $validatedData['assigned_at'] = !empty($validatedData['followup_by_id']) ? now() : null;
            $member->update($validatedData);
            $this->clearCache();
            DB::commit();
            return $member->fresh(['assignedTo']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteMembers(array $ids): array
    {
        try {
            DB::beginTransaction();
            DB::table('unit_user')->whereIn('user_id', $ids)->delete();
            $deletedCount = User::whereIn('id', $ids)->delete();
            DB::commit();
            $this->clearCache();
            return [
                'deleted_count' => $deletedCount,
                'failed_count' => count($ids) - $deletedCount,
                'success' => $deletedCount > 0,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deleteMember(User $member): bool
    {
        return DB::transaction(function () use ($member) {
            $deleted = $member->delete();
            $member->units()->detach();
            $this->clearCache();
            return $deleted;
        });
    }
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
            $recipients = [
                [
                    'name' => $assignedUser->first_name,
                    'email' => $assignedUser->email
                ]
            ];

            $this->mailService->sendAssignedMemberEmail($recipients);

        } catch (\Exception $e) {
        }
    }
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        foreach (['admin', 'leader', 'member'] as $role) {
            Cache::forget(self::CACHE_KEY . "_role_{$role}");
        }
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
