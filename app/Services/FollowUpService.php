<?php

namespace App\Services;

use App\Models\FirstTimer;
use App\Models\FollowupFeedback;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FollowUpService
{

    public function getFollowUpsByFirstTimer(FirstTimer $firstTimer): Collection
    {
        $cacheKey = "first_timer_{$firstTimer->id}_followups";
        return Cache::remember($cacheKey, now()->addMinutes(30), fn() => $firstTimer->followupFeedbacks->load(['followupable', 'createdBy']));
    }
    public function createFollowUp(array $data): FollowupFeedback
    {
        return DB::transaction(function () use ($data) {
            $followUp = FollowupFeedback::create([
                'followupable_type' => $data['followupable_type'],
                'followupable_id' => $data['followupable_id'],
                'user_id' => auth()->id(),
                'note' => $data['note'],
                'type' => $data['type'] ?? null,
                'service_date' => $data['service_date'] ?? null,
            ]);
            $followUp->load(['createdBy', 'followupable']);
            $this->clearCache($data['followupable_id']);
            return $followUp;
        });
    }

    private function clearCache(int $firstTimerId): void
    {
        Cache::forget("first_timer_{$firstTimerId}_followups");
    }
}
