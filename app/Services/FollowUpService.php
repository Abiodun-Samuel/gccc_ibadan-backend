<?php

namespace App\Services;

use App\Models\FollowupFeedback;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FollowUpService
{
    public function getFollowUps(User $user): Collection
    {
        $cacheKey = "user_{$user->id}_followups";

        return Cache::remember($cacheKey, now()->addMinutes(30), fn() => $user->followupFeedbacks()->with(['createdBy', 'user'])->latest()->get());
    }

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
            $this->clearCache($data['user_id']);

            return $followUp;
        });
    }
    private function clearCache(int $userId): void
    {
        Cache::forget("user_{$userId}_followups");
    }
}
