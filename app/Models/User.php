<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

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
        'role',
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
        'email_verified_at',
        'attendance_badge',
        'last_badge_month',
        'last_badge_year',
        'assigned_to_user_id',
        'assigned_at',
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
            'assigned_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'attendance_badge' => 'integer',
            'last_badge_month' => 'integer',
            'last_badge_year' => 'integer',
        ];
    }
    // scopes
    public function scopeAdmins($query)
    {
        return $query->role(RoleEnum::ADMIN->value);
    }
    public function scopeLeaders($query)
    {
        return $query->role(RoleEnum::LEADER->value);
    }
    public function scopeMembers($query)
    {
        return $query->role(RoleEnum::MEMBER->value);
    }
    public function scopeWithFullProfile($query)
    {
        return $query->with([
            'units.leader',
            'units.assistantLeader',
            'units.members',
            'permissions',
            'roles',
            'ledUnits',
            'assistedUnits',
            'memberUnits',
        ]);
    }
    public function loadFullProfile()
    {
        return $this->load([
            'units.leader',
            'units.assistantLeader',
            'units.members',
            'permissions',
            'roles',
            'ledUnits',
            'assistedUnits',
            'memberUnits',
            'assignedTo'
        ]);
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
        return $this->hasMany(FirstTimer::class, 'assigned_to_member_id')->orderByDesc('date_of_visit');
    }
    public function scopeInPeriod(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
    }
    public function assignedAbsentees()
    {
        return $this->hasMany(AbsenteeAssignment::class, 'leader_id');
    }
    public function absenteeAssignments()
    {
        return $this->hasMany(AbsenteeAssignment::class, 'user_id');
    }

    public function followupFeedbacks(): MorphMany
    {
        return $this->morphMany(FollowupFeedback::class, 'followupable')->latest();
    }
    public function createdFollowupFeedbacks(): HasMany
    {
        return $this->hasMany(FollowupFeedback::class, 'user_id')->latest();
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'assigned_to_user_id');
    }
}
