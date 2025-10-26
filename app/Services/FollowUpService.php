<?php

namespace App\Services;

use App\Models\FollowupFeedback;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FollowUpService
{
    public function getFollowUps(Model $model): Collection
    {
        $type = strtolower(class_basename($model));
        $cacheKey = "{$type}_{$model->id}_followups";

        return Cache::remember($cacheKey, now()->addMinutes(30), fn() => $model->followupFeedbacks->load(['followupable', 'createdBy']));
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
            $this->clearCache($data['followupable_type'], $data['followupable_id']);

            return $followUp;
        });
    }
    private function clearCache(string $followupableType, int $followupableId): void
    {
        $type = strtolower(class_basename($followupableType));
        Cache::forget("{$type}_{$followupableId}_followups");
    }
}
