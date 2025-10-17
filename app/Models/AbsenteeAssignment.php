<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenteeAssignment extends Model
{
    protected $table = 'absentee_assignments';
    protected $fillable = [
        'leader_id',
        'user_id',
        'attendance_id',
        'service_id',
        'attendance_date',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function followupFeedbacks()
    {
        return $this->hasManyThrough(
            FollowupFeedback::class,
            User::class,
            'id', // Foreign key on users table
            'followupable_id', // Foreign key on followup_feedbacks table
            'user_id', // Local key on absentee_assignments table
            'id' // Local key on users table
        )->where('followup_feedbacks.followupable_type', User::class);
    }

    public function memberFollowupFeedbacks()
    {
        return $this->user->followupFeedbacks();
    }
}
