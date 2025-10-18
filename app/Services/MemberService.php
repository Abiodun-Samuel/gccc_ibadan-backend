<?php

namespace App\Services;

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
            fn() => User::get()
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
                default => throw new InvalidArgumentException("Invalid role: {$role}"),
            }
        );
    }
    public function findMember(User $user): User
    {
        return $user->loadFullProfile();
    }

    public function createMember(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $member = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'gender' => $data['gender'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'address' => $data['address'] ?? null,
                'community' => $data['community'] ?? null,
                'worker' => $data['worker'] ?? null,
                'status' => $data['status'] ?? 'active',
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'country' => $data['country'] ?? null,
                'city_or_state' => $data['city_or_state'] ?? null,
                'facebook' => $data['facebook'] ?? null,
                'instagram' => $data['instagram'] ?? null,
                'linkedin' => $data['linkedin'] ?? null,
                'twitter' => $data['twitter'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            $this->clearCache();
            return $member;
        });
    }

    public function updateMember(User $member, array $validatedData): User
    {
        return DB::transaction(function () use ($member, $validatedData) {
            if (!empty($validatedData['phone_number'])) {
                $validatedData['password'] = Hash::make($validatedData['phone_number']);
            }

            if (!empty($validatedData['assigned_to_user_id'])) {
                $assignedUser = User::select('id', 'first_name', 'email')
                    ->find($validatedData['assigned_to_user_id']);

                if ($assignedUser) {
                    $recipients = [
                        [
                            'name' => $assignedUser->first_name,
                            'email' => $assignedUser->email
                        ]
                    ];
                    $this->mailService->sendAssignedMemberEmail($recipients);
                }
            }

            // Set assigned_at timestamp if assigned_to_user_id is being set
            $validatedData['assigned_at'] = !empty($validatedData['assigned_to_user_id']) ? now() : null;

            // Update the member
            $member->update($validatedData);

            // Clear any cached data
            $this->clearCache();

            // Return the fresh model with the assignedTo relationship loaded
            return $member->fresh('assignedTo');
        });
    }

    public function deleteMember(User $member): bool
    {
        return DB::transaction(function () use ($member) {
            $member->units()->detach();
            $deleted = $member->delete();
            $this->clearCache();
            return $deleted;
        });
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        foreach (['admin', 'leader', 'member'] as $role) {
            Cache::forget(self::CACHE_KEY . "_role_{$role}");
        }
    }
}
