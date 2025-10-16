<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FollowupFeedback extends Model
{
    protected $table = 'followup_feedbacks';
    protected $fillable = [
        'followupable_type',
        'followupable_id',
        'user_id',
        'note',
        'type',
        'service_date'
    ];
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }

    public function followupable(): MorphTo
    {
        return $this->morphTo();
    }
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function isForFirstTimer(): bool
    {
        return $this->followupable_type === FirstTimer::class;
    }
    public function scopeForFirstTimers(Builder $query): Builder
    {
        return $query->where('followupable_type', FirstTimer::class);
    }
    public function scopeForMembers(Builder $query): Builder
    {
        return $query->where('followupable_type', User::class);
    }
}
