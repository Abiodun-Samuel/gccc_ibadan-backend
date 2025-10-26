<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    protected $casts = [
        'attendance_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'first_name', 'last_name', 'email', 'gender', 'phone_number']);
    }
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->select([
            'id',
            'name',
            'day_of_week',
            'description',
            'start_time',
            'is_recurring',
            'service_date',
        ]);
    }
    // Query scopes for common filters
    public function scopePresent(Builder $query): Builder
    {
        return $query->where('status', 'present');
    }
    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', 'absent');
    }
    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('attendance_date', $date);
    }
    public function scopeForDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('attendance_date', '>=', now()->subDays($days));
    }
}
