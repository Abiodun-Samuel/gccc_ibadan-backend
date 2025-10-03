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
}
