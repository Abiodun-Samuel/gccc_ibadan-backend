<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'password',
        'gender',
        'address',
        'date_of_birth',
        'community',
        'worker',
        'avatar',
        'status',
        'country',
        'city_or_state',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'education',
        'field_of_study',
        'occupation',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pivot'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
        ];
    }
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar
            ? Storage::disk('public')->url($this->avatar)
            : null;
    }
    public function getRolePermissions()
    {
        return $this->roles->flatMap(fn($role) => $role->permissions)->unique('id');
    }
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->latest('attendance_date');
    }
    public function recentAttendances(int $days = 30): HasMany
    {
        return $this->attendances()
            ->where('attendance_date', '>=', now()->subDays($days));
    }
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class);
    }
    public function ledUnits()
    {
        return $this->hasMany(Unit::class, 'leader_id');
    }

    public function assistedUnits()
    {
        return $this->hasMany(Unit::class, 'assistant_leader_id');
    }

    public function memberUnits()
    {
        return $this->belongsToMany(Unit::class, 'unit_user')
            ->withTimestamps();
    }
    public function assignedFirstTimers()
    {
        // where status is active
        return $this->hasMany(FirstTimer::class, 'assigned_to_member_id');
    }
    public function scopeInPeriod(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
    }
}
