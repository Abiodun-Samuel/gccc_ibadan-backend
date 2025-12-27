<?php

namespace App\Services;

use App\Models\FollowupFeedback;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FollowUpService
{
    /**
     * Get all follow-ups for a specific user
     *
     * @param User $user
     * @return Collection
     */
    public function getFollowUps(User $user): Collection
    {
        return $user->followupFeedbacks()
            ->with(['createdBy', 'user'])
            ->latest()
            ->get();
    }

    /**
     * Create a new follow-up feedback
     *
     * @param array $data
     * @return FollowupFeedback
     */
    public function createFollowUp(array $data): FollowupFeedback
    {
        return DB::transaction(function () use ($data) {
            $followUp = FollowupFeedback::create([
                'created_by' => $data['created_by'],
                'user_id' => $data['user_id'],
                'note' => $data['note'],
                'type' => $data['type'] ?? null,
                'service_date' => $data['service_date'] ?? null,
            ]);

            $followUp->load(['createdBy', 'user']);

            return $followUp;
        });
    }
}
