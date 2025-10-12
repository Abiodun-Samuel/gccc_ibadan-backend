<?php

namespace App\Services;

use App\Models\FirstTimer;
use App\Models\FirstTimerFollowUp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FollowUpService
{

    public function getFollowUpsByFirstTimer(FirstTimer $firstTimer): Collection
    {
        $cacheKey = "first_timer_{$firstTimer->id}_followups";
        return Cache::remember($cacheKey, now()->addMinutes(30), fn() => FirstTimerFollowUp::where('first_timer_id', $firstTimer->id)
            ->with(['user', 'firstTimer'])
            ->latest()
            ->get());
    }
    public function createFollowUp(FirstTimer $firstTimer, array $data): FirstTimerFollowUp
    {
        return DB::transaction(function () use ($firstTimer, $data) {
            $followUp = FirstTimerFollowUp::create([
                'first_timer_id' => $firstTimer->id,
                'user_id' => auth()->id(),
                'note' => $data['note'],
                'type' => $data['type'] ?? null,
            ]);
            $followUp->load(['user', 'firstTimer']);
            $this->clearCache($firstTimer->id);
            return $followUp;
        });
    }

    private function clearCache(int $firstTimerId): void
    {
        Cache::forget("first_timer_{$firstTimerId}_followups");
    }
}
