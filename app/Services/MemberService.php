<?php

namespace App\Services;

use App\DTOs\MemberData;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberService
{
    private const CACHE_KEY = 'members_list';

    public function listMembers(
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ) {
        $query = User::with('units')
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'gender',
                'community',
                'status',
                'avatar',
                'worker',
                'created_at'
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
        // return Cache::remember(
        //     self::CACHE_KEY . ":{$perPage}:{$search}:{$sortBy}:{$sortDir}",
        //     60,
        //     function () use ($perPage, $search, $sortBy, $sortDir) {
        //         $query = User::with('units')
        //             ->select([
        //                 'id',
        //                 'first_name',
        //                 'last_name',
        //                 'email',
        //                 'phone_number',
        //                 'gender',
        //                 'community',
        //                 'status',
        //                 'avatar',
        //                 'worker',
        //                 'created_at'
        //             ]);

        //         if ($search) {
        //             $query->where(function ($q) use ($search) {
        //                 $q->where('first_name', 'like', "%{$search}%")
        //                     ->orWhere('last_name', 'like', "%{$search}%")
        //                     ->orWhere('email', 'like', "%{$search}%")
        //                     ->orWhere('phone_number', 'like', "%{$search}%");
        //             });
        //         }

        //         return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
        //     }
        // );
    }

    public function createMember(MemberData $data): User
    {
        return DB::transaction(function () use ($data) {
            $member = User::create([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone_number' => $data->phone_number,
                'gender' => $data->gender,
                'avatar' => $data->avatar,
                'address' => $data->address,
                'community' => $data->community,
                'worker' => $data->worker,
                'status' => $data->status,
                'date_of_birth' => $data->date_of_birth,
                'date_of_visit' => $data->date_of_visit,
                'country' => $data->country,
                'city_or_state' => $data->city_or_state,
                'facebook' => $data->facebook,
                'instagram' => $data->instagram,
                'linkedin' => $data->linkedin,
                'twitter' => $data->twitter,
                'password' => Hash::make($data->password),
            ]);

            // Sync units
            $unitData = array_fill_keys($data->unit_ids, ['is_leader' => false]);
            foreach ($data->leader_unit_ids as $leaderUnitId) {
                $unitData[$leaderUnitId] = ['is_leader' => true];
            }
            $member->units()->sync($unitData);

            Cache::forget(self::CACHE_KEY);

            return $member;
        });
    }

    public function updateMember(User $member, MemberData $data): User
    {
        return DB::transaction(function () use ($member, $data) {
            $updateData = [
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone_number' => $data->phone_number,
                'avatar' => $data->avatar,
                'gender' => $data->gender,
                'address' => $data->address,
                'community' => $data->community,
                'worker' => $data->worker,
                'status' => $data->status,
                'date_of_birth' => $data->date_of_birth,
                'date_of_visit' => $data->date_of_visit,
                'country' => $data->country,
                'city_or_state' => $data->city_or_state,
                'facebook' => $data->facebook,
                'instagram' => $data->instagram,
                'linkedin' => $data->linkedin,
                'twitter' => $data->twitter,
            ];

            if ($data->password) {
                $updateData['password'] = Hash::make($data->password);
            }

            $member->update($updateData);

            // Sync units
            $unitData = array_fill_keys($data->unit_ids, ['is_leader' => false]);
            foreach ($data->leader_unit_ids as $leaderUnitId) {
                $unitData[$leaderUnitId] = ['is_leader' => true];
            }
            $member->units()->sync($unitData);

            Cache::forget(self::CACHE_KEY);

            return $member->fresh();
        });
    }

    public function bulkUpsertMembers(array $membersData): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($membersData, &$created, &$updated) {
            collect($membersData)->chunk(50)->each(function ($chunk) use (&$created, &$updated) {
                foreach ($chunk as $data) {
                    // Ensure each record has email
                    if (empty($data['email'])) {
                        continue; // skip invalid record
                    }
                    // Try to find existing member by email
                    $member = User::where('email', $data['email'])->first();

                    $dto = MemberData::fromArray($data);

                    if ($member) {
                        // Update existing member
                        $this->upsertMember($dto, $member);
                        $updated++;
                    } else {
                        // Create new member
                        $this->upsertMember($dto);
                        $created++;
                    }
                }
            });
        });

        // Clear cached members listing
        Cache::forget(self::CACHE_KEY);

        return [
            'created' => $created,
            'updated' => $updated,
            'total_processed' => $created + $updated,
        ];
    }

    public function upsertMember(MemberData $dto, ?User $existingMember = null): User
    {
        return DB::transaction(function () use ($dto, $existingMember) {
            $member = $existingMember ?? new User();

            $member->fill([
                'first_name' => $dto->first_name,
                'last_name' => $dto->last_name,
                'email' => $dto->email,
                'phone_number' => $dto->phone_number,
                'gender' => $dto->gender,
                'address' => $dto->address,
                'community' => $dto->community,
                'worker' => $dto->worker,
                'status' => $dto->status,
                'date_of_birth' => $dto->date_of_birth,
                'date_of_visit' => $dto->date_of_visit,
                'country' => $dto->country,
                'city_or_state' => $dto->city_or_state,
                'facebook' => $dto->facebook,
                'instagram' => $dto->instagram,
                'linkedin' => $dto->linkedin,
                'twitter' => $dto->twitter,
            ]);

            // Set password only on create or when explicitly given
            if (!empty($dto->phone_number)) {
                $member->password = bcrypt($dto->phone_number);
            }

            $member->save();

            // Sync units if provided
            // if (!empty($dto->units)) {
            //     $syncData = collect($dto->units)->mapWithKeys(function ($unit) {
            //         return [
            //             $unit['id'] => ['is_leader' => $unit['is_leader'] ?? false],
            //         ];
            //     });
            //     $member->units()->sync($syncData);
            // }

            // return $member->load('units');
        });
    }



    public function deleteMember(User $member): void
    {
        $member->units()->detach();
        $member->delete();
        Cache::forget(self::CACHE_KEY);
    }
}
