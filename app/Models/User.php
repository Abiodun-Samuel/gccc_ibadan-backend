<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;
    protected $guard_name = 'api';


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
        ];
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
    public function units()
    {
        return $this->belongsToMany(Unit::class)
            ->withPivot(['is_leader', 'is_asst_leader'])
            ->withTimestamps();
    }
    // public function leadingUnits()
    // {
    //     return $this->units()->wherePivot('is_leader', true);
    // }
    public function assignedFirstTimers()
    {
        // where status is active
        return $this->hasMany(FirstTimer::class, 'assigned_to_member_id');
    }
}
