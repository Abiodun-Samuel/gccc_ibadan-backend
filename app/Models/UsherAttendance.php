<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsherAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_day',
        'male',
        'female',
        'children',
        'total_attendance',
        'service_date',
        'service_day_desc',
    ];
    protected $casts = [
        'service_date' => 'date',
    ];
    protected static function booted(): void
    {
        static::saving(function ($attendance) {
            $attendance->total_attendance = $attendance->male + $attendance->female + $attendance->children;
        });
    }
}
