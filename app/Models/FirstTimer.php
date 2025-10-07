<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FirstTimer extends Model
{
    protected $hidden = ['pivot'];
    protected $fillable = [
        'name',
        'phone_number',
        'email',
        'status',
        'located_in_ibadan',
        'interest',
        'born_again',
        'whatsapp_interest',
        'address',
        'date_of_visit',
        'date_of_birth',
        'occupation',
        'is_student',
        'invited_by',
        'service_experience',
        'prayer_point',
        'notes',
        'week_ending',
        'visitation_report',
        'pastorate_call',
        'gender',
        'how_did_you_learn',
        'friend_family',
        'follow_up_status_id',
        'assigned_to_member_id',
        'assigned_at',
    ];

    protected $casts = [
        'week_ending' => 'date',
        'date_of_birth' => 'date',
        'date_of_visit' => 'date',
        'assigned_at' => 'datetime',
        'located_in_ibadan' => 'boolean',
        'whatsapp_interest' => 'boolean',
        'is_student' => 'boolean'
    ];
    public function followUpStatus()
    {
        return $this->belongsTo(FollowUpStatus::class, 'follow_up_status_id');
    }
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_member_id');
    }
    public function followupNotes()
    {
        return $this->hasMany(FirstTimerFollowUp::class);
    }
    public function scopeInPeriod(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('date_of_visit', [$startDate->startOfDay(), $endDate->endOfDay()]);
    }
}
