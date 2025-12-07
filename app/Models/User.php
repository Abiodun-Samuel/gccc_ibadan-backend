<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /*--------------------------------------------------------------
    | Mass Assignment
    --------------------------------------------------------------*/
    protected $fillable = [
        'followup_by_id',
        'follow_up_status_id',
        'first_name',
        'last_name',
        'email',
        'avatar',
        'secondary_avatar',
        'phone_number',
        'gender',
        'status',
        'located_in_ibadan',
        'membership_interest',
        'born_again',
        'whatsapp_interest',
        'is_student',
        'address',
        'how_did_you_learn',
        'invited_by',
        'service_experience',
        'prayer_point',
        'notes',
        'community',
        'country',
        'city_or_state',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'education',
        'field_of_study',
        'occupation',
        'visitation_report',
        'pastorate_call',
        'week_ending',
        'assigned_at',
        'date_of_birth',
        'date_of_visit',
        'email_verified_at',
        'password',
        'attendance_badges',
    ];

    protected array $completionFields = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'gender',
        'country',
        'education',
        'field_of_study',
        'occupation',
        'city_or_state',
        'avatar',
        'address',
        'date_of_birth',
        'community'
    ];

    /*--------------------------------------------------------------
    | Hidden Attributes
    --------------------------------------------------------------*/
    protected $hidden = [
        'password',
        'remember_token',
        'pivot',
    ];

    /*--------------------------------------------------------------
    | Attribute Casting
    --------------------------------------------------------------*/
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'assigned_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'date_of_visit' => 'date',
            'week_ending' => 'date',
            'located_in_ibadan' => 'boolean',
            'whatsapp_interest' => 'boolean',
            'is_student' => 'boolean',
            'attendance_badges' => AsCollection::class,
        ];
    }
    public function isProfileCompleted(): bool
    {
        return collect($this->completionFields)
            ->every(fn($field) => !empty($this->{$field}));
    }
    public function getProfileCompletionPercentAttribute(): int
    {
        $filled = collect($this->completionFields)
            ->filter(fn($field) => !empty($this->{$field}))
            ->count();

        return intval(($filled / count($this->completionFields)) * 100);
    }

    /*--------------------------------------------------------------
    | Query Scopes
    --------------------------------------------------------------*/

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
        return $query->role(RoleEnum::MEMBER->value)->whereNot('status', 'disabled');
    }
    public function scopeFirstTimers($query)
    {
        return $query->role(RoleEnum::FIRST_TIMER->value);
    }
    public function scopeActivelyFollowedFirstTimers($query)
    {
        return $query->firstTimers()->whereNotIn('status', ['disabled', 'deactivated'])
            ->whereNotIn('follow_up_status_id', [
                FollowUpStatus::OPT_OUT_ID,
                FollowUpStatus::INTEGRATED_ID,
            ]);
    }
    public function scopeWithFullProfile($query)
    {
        return $query->with(['units.leader', 'units.assistantLeader', 'units.assistantLeader2', 'units.members', 'roles']);
    }
    public function loadFullProfile()
    {
        return $this->load(['units.leader', 'units.assistantLeader', 'units.assistantLeader2', 'units.members', 'roles']);
    }

    public function scopeBirthdayThisWeek(Builder $query): Builder
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return $query->select([
            'id',
            'email',
            'first_name',
            'last_name',
            'avatar',
            'phone_number',
            'date_of_birth'
        ])
            ->whereNotNull('date_of_birth')
            ->where(function ($q) use ($startOfWeek, $endOfWeek) {
                // Handle birthdays in the same year
                $q->whereRaw(
                    "DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN ? AND ?",
                    [
                        $startOfWeek->format('m-d'),
                        $endOfWeek->format('m-d')
                    ]
                );

                // Handle year-end edge case (e.g., Dec 30 - Jan 5)
                if ($startOfWeek->month > $endOfWeek->month) {
                    $q->orWhereRaw(
                        "DATE_FORMAT(date_of_birth, '%m-%d') >= ?",
                        [$startOfWeek->format('m-d')]
                    )->orWhereRaw(
                        "DATE_FORMAT(date_of_birth, '%m-%d') <= ?",
                        [$endOfWeek->format('m-d')]
                    );
                }
            })
            ->orderByRaw("DATE_FORMAT(date_of_birth, '%m-%d')");
    }
    // public static function getCachedBirthdaysThisWeek()
    // {
    //     // $cacheKey = 'birthdays_week_' . Carbon::now()->startOfWeek()->format('Y-m-d');
    //     // $expiresAt = Carbon::now()->endOfWeek();
    //     return self::birthdayThisWeek()->get();
    //     // return Cache::remember($cacheKey, $expiresAt, function () {
    //     // });
    // }

    /*--------------------------------------------------------------
    | Relationships → Hierarchical / User-based
    --------------------------------------------------------------*/

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'followup_by_id');
    }
    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'followup_by_id');
    }

    /*--------------------------------------------------------------
    | Relationships → Follow-up / Absentee Tracking
    --------------------------------------------------------------*/
    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
    /** The follow-up status for this user */
    public function followUpStatus()
    {
        return $this->belongsTo(FollowUpStatus::class, 'follow_up_status_id');
    }

    /** Absentee assignments where this user is the leader */
    public function assignedAbsentees()
    {
        return $this->hasMany(AbsenteeAssignment::class, 'leader_id');
    }

    /** Absentee assignments linked to this user */
    public function absenteeAssignments()
    {
        return $this->hasMany(AbsenteeAssignment::class, 'user_id');
    }
    public function followUpFeedbacks()
    {
        return $this->hasMany(FollowupFeedback::class, 'user_id')->latest();
    }

    public function createdFollowUpFeedbacks()
    {
        return $this->hasMany(FollowupFeedback::class, 'created_by');
    }

    /*--------------------------------------------------------------
    | Relationships → Attendance & Units
    --------------------------------------------------------------*/

    /** Attendance records for this user */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Units where this user belongs */
    public function units()
    {
        return $this->belongsToMany(Unit::class)->withCount('members');
    }
}
