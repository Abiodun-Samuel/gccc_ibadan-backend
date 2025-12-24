<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PicnicRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'games',
        'support_amount',
        'registered_at'
    ];

    protected $casts = [
        'games' => 'array',
        'support_amount' => 'decimal:2',
        'registered_at' => 'datetime',
        'year' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrentYear($query)
    {
        return $query->where('year', now()->year);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public static function isLimitReached(int $year): bool
    {
        return static::forYear($year)->count() >= config('picnic.max_registrations_per_year');
    }

    public static function availableSlots(int $year): int
    {
        $registered = static::forYear($year)->count();
        $max = config('picnic.max_registrations_per_year');
        return max(0, $max - $registered);
    }
}
