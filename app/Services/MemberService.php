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

    public function getAllMembers(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => User::withFullProfile()->get()
        );
    }

    public function getUsersByRole(string $role): Collection
    {
        $cacheKey = self::CACHE_KEY . "_role_{$role}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => match ($role) {
                'admin' => User::admins()->withFullProfile()->get(),
                'leader' => User::leaders()->withFullProfile()->get(),
                'member' => User::members()->withFullProfile()->get(),
                default => throw new InvalidArgumentException("Invalid role: {$role}"),
            }
        );
    }

    public function findMember(int $id): User
    {
        return User::withFullProfile()->findOrFail($id);
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

    public function updateMember(User $member, array $data): User
    {
        return DB::transaction(function () use ($member, $data) {
            $updateData = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'gender' => $data['gender'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'address' => $data['address'] ?? null,
                'community' => $data['community'] ?? null,
                'worker' => $data['worker'] ?? null,
                'status' => $data['status'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'country' => $data['country'] ?? null,
                'city_or_state' => $data['city_or_state'] ?? null,
                'facebook' => $data['facebook'] ?? null,
                'instagram' => $data['instagram'] ?? null,
                'linkedin' => $data['linkedin'] ?? null,
                'twitter' => $data['twitter'] ?? null,
            ], fn($value) => $value !== null);

            if (!empty($data['phone_number'])) {
                $updateData['password'] = Hash::make($data['phone_number']);
            }

            $member->update($updateData);

            $this->clearCache();
            return $member;
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
