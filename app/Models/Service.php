<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'day_of_week',
        'start_time',
        'is_recurring',
        'service_date',
    ];
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->latest('attendance_date');
    }
    public function attendancesForDate(string $date): HasMany
    {
        return $this->attendances()->whereDate('attendance_date', $date);
    }
}
