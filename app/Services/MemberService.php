<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberService
{
    private const CACHE_KEY = 'members_list';
    private const CACHE_TTL = 3600;

    public function getAllMembers(): Collection
    {
        return User::with(['units', 'assignedFirstTimers', 'permissions'])->get();
        // return Cache::remember(
        //     self::CACHE_KEY,
        //     self::CACHE_TTL,
        //     fn() =>
        // );
    }

    public function findMember(int $id): User
    {
        return User::with(['units', 'assignedFirstTimers'])->findOrFail($id);
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
            return $member->load('units');
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
            return $member->fresh('units');
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

    public function bulkCreateMembers(array $membersData): array
    {
        $results = [
            'created' => [],
            'failed' => [],
            'total_processed' => count($membersData),
        ];

        DB::transaction(function () use ($membersData, &$results) {
            foreach ($membersData as $index => $memberData) {
                try {
                    // Check for duplicate email
                    if (User::where('email', $memberData['email'])->exists()) {
                        $results['failed'][] = [
                            'index' => $index,
                            'email' => $memberData['email'],
                            'error' => 'Email already exists'
                        ];
                        continue;
                    }

                    $member = User::create([
                        'first_name' => $memberData['first_name'],
                        'last_name' => $memberData['last_name'],
                        'email' => $memberData['email'],
                        'phone_number' => $memberData['phone_number'] ?? null,
                        'gender' => $memberData['gender'] ?? null,
                        'avatar' => $memberData['avatar'] ?? null,
                        'address' => $memberData['address'] ?? null,
                        'community' => $memberData['community'] ?? null,
                        'worker' => $memberData['worker'] ?? false,
                        'status' => $memberData['status'] ?? 'active',
                        'date_of_birth' => $memberData['date_of_birth'] ?? null,
                        'country' => $memberData['country'] ?? null,
                        'city_or_state' => $memberData['city_or_state'] ?? null,
                        'facebook' => $memberData['facebook'] ?? null,
                        'instagram' => $memberData['instagram'] ?? null,
                        'linkedin' => $memberData['linkedin'] ?? null,
                        'twitter' => $memberData['twitter'] ?? null,
                        'password' => Hash::make($memberData['phone_number']),
                    ]);

                    $results['created'][] = [
                        'index' => $index,
                        'id' => $member->id,
                        'email' => $member->email
                    ];

                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'index' => $index,
                        'email' => $memberData['email'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        $this->clearCache();
        return $results;
    }

    public function bulkUpdateMembersByEmail(array $membersData): array
    {
        $results = [
            'updated' => [],
            'not_found' => [],
            'failed' => [],
            'total_processed' => count($membersData),
        ];

        DB::transaction(function () use ($membersData, &$results) {
            foreach ($membersData as $index => $memberData) {
                try {
                    $member = User::where('id', $memberData['id'])->first();

                    if (!$member) {
                        $results['not_found'][] = [
                            'index' => $index,
                            'id' => $memberData['id'],
                        ];
                        continue;
                    }

                    $updateData = array_filter([
                        'first_name' => $memberData['first_name'] ?? null,
                        'email' => $memberData['email'],
                        'last_name' => $memberData['last_name'] ?? null,
                        'phone_number' => $memberData['phone_number'] ?? null,
                        'gender' => $memberData['gender'] ?? null,
                        'avatar' => $memberData['avatar'] ?? null,
                        'address' => $memberData['address'] ?? null,
                        'community' => $memberData['community'] ?? null,
                        'worker' => $memberData['worker'],
                        'status' => $memberData['status'] ?? null,
                        'date_of_birth' => $memberData['date_of_birth'] ?? null,
                        'country' => $memberData['country'],
                        'city_or_state' => $memberData['city_or_state'] ?? null,
                        'facebook' => $memberData['facebook'] ?? null,
                        'instagram' => $memberData['instagram'] ?? null,
                        'linkedin' => $memberData['linkedin'] ?? null,
                        'twitter' => $memberData['twitter'] ?? null,
                    ], fn($value) => $value !== null);

                    if (!empty($memberData['phone_number'])) {
                        $updateData['password'] = Hash::make($memberData['phone_number']);
                    }

                    if (!empty($updateData)) {
                        $member->update($updateData);
                    }

                    $results['updated'][] = [
                        'index' => $index,
                        'id' => $member->id,
                        'email' => $member->email
                    ];

                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'index' => $index,
                        'email' => $memberData['email'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        $this->clearCache();
        return $results;
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
