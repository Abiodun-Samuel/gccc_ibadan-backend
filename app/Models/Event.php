<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'image',
        'status',
        'registration_link',
        'registration_deadline',
        'audio_streaming_link',
        'video_streaming_link',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_deadline' => 'datetime',
    ];

    protected $appends = ['formatted_date', 'formatted_time'];

    /**
     * Boot method to auto-update status based on dates
     */
    protected static function booted()
    {
        static::saving(function ($event) {
            $event->updateStatus();
        });
    }

    /**
     * Update event status based on current date
     */
    public function updateStatus(): void
    {
        $now = Carbon::now()->startOfDay();
        $startDate = Carbon::parse($this->start_date)->startOfDay();
        $endDate = $this->end_date
            ? Carbon::parse($this->end_date)->endOfDay()
            : Carbon::parse($this->start_date)->endOfDay();

        if ($now->lt($startDate)) {
            $this->status = 'upcoming';
        } elseif ($now->between($startDate, $endDate)) {
            $this->status = 'ongoing';
        } else {
            $this->status = 'past';
        }
    }

    /**
     * Scope: Upcoming events
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    /**
     * Scope: Ongoing events
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    /**
     * Scope: Past events
     */
    public function scopePast($query)
    {
        return $query->where('status', 'past');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        if (in_array($status, ['upcoming', 'ongoing', 'past'])) {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope: Search by title or description
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }
        return $query;
    }

    /**
     * Scope: Order by date
     */
    public function scopeOrderByDate($query, $direction = 'asc')
    {
        return $query->orderBy('start_date', $direction);
    }

    /**
     * Accessor: Formatted date range
     */
    public function getFormattedDateAttribute(): string
    {
        if ($this->end_date && !$this->start_date->isSameDay($this->end_date)) {
            return $this->start_date->format('M d') . ' - ' . $this->end_date->format('M d, Y');
        }
        return $this->start_date->format('F d, Y');
    }



    /**
     * Accessor: Formatted time range
     */
    public function getFormattedTimeAttribute(): ?string
    {
        if (!$this->start_time) {
            return null;
        }

        $start = Carbon::parse($this->start_time)->format('g:i A');

        if ($this->end_time) {
            $end = Carbon::parse($this->end_time)->format('g:i A');
            return "{$start} - {$end}";
        }

        return $start;
    }
    // public function getFormattedTimeAttribute(): string
    // {
    //     $start = Carbon::parse($this->start_time)->format('g:i A');
    //     if ($this->end_time) {
    //         $end = Carbon::parse($this->end_time)->format('g:i A');
    //         return "{$start} - {$end}";
    //     }
    //     return $start;
    // }

    /**
     * Check if registration is open
     */
    public function isRegistrationOpen(): bool
    {
        if (!$this->registration_link) {
            return false;
        }

        if ($this->registration_deadline) {
            return Carbon::now()->lt($this->registration_deadline);
        }

        return $this->status === 'upcoming';
    }

    /**
     * Check if event has streaming available
     */
    public function hasStreaming(): bool
    {
        return !empty($this->audio_streaming_link) || !empty($this->video_streaming_link);
    }

    public function scopeAfterToday(Builder $query): Builder
    {
        return $query->whereDate('start_date', '>', Carbon::today());
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('start_date', Carbon::today());
    }

    public function scopeOrderByDateTime(Builder $query): Builder
    {
        return $query
            ->orderBy('start_date', 'asc')
            ->orderByRaw('ISNULL(start_time) ASC')
            ->orderBy('start_time', 'asc');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
