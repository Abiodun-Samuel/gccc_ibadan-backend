<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'attendance_date',
        'status',
        'mode',
    ];
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    // Query scopes
    public function scopeInPeriod(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('attendance_date', [$startDate->startOfDay(), $endDate->endOfDay()]);
    }
}
