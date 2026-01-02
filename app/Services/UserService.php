<?php

namespace App\Services;

use App\Models\AbsenteeAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get assigned absentees for a leader
     */
    public function getAssignedAbsenteesForLeader(int $leaderId)
    {
        return AbsenteeAssignment::with(['user', 'attendance.service'])
            ->where('leader_id', $leaderId)
            ->get();
    }

    /**
     * Get assigned members for a leader
     */
    public function getAssignedMembers(User $user)
    {
        return $user->assignedUsers()
            ->members()
            // ->with(['followUpStatus', 'assignedTo'])
            // ->latest('date_of_visit')
            ->get();

        // $user->load(['assignedUsers'])->members();
        // return $user;
    }

    /**
     * Update user profile with avatar handling
     */
    public function updateProfile(User $user, array $data, ?string $folder = 'users'): User
    {
        return DB::transaction(function () use ($user, $data, $folder) {
            // Handle avatar upload
            if (!empty($data['avatar'])) {
                $data['avatar'] = $this->handleAvatarUpload($user, $data['avatar'], $folder);
            }

            // If phone number is provided, use it as password
            if (!empty($data['phone_number'])) {
                $data['password'] = bcrypt($data['phone_number']);
            }

            // Update user with validated data
            $user->update($data);

            // Refresh and load full profile
            $user->fresh();
            $user->loadFullProfile();

            return $user;
        });
    }

    /**
     * Handle avatar upload and delete old avatar if exists
     */
    protected function handleAvatarUpload(User $user, $avatarFile, string $folder): string
    {
        // Delete old avatar if exists
        if ($user->avatar !== null) {
            $this->uploadService->delete($user->avatar);
        }

        // Upload new avatar
        return $this->uploadService->upload($avatarFile, $folder);
    }

    public function getUsersForBulkEmail(array $userIds): Collection
    {
        return User::whereIn('id', $userIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select([
                'id',
                'email',
                'first_name',
                'last_name'
            ])
            ->get();
    }
}
