<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name', 'leader_id', 'assistant_leader_id'];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function assistantLeader()
    {
        return $this->belongsTo(User::class, 'assistant_leader_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'unit_user')
            ->withTimestamps()
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.phone_number', 'users.gender');
    }

    // Boot hooks to ensure consistency
    protected static function booted(): void
    {
        static::saved(function (Unit $unit) {
            $unit->syncLeaderAsMember();
            $unit->syncAssistantLeaderAsMember();
        });
    }

    protected function syncLeaderAsMember(): void
    {
        if ($this->leader_id && !$this->members()->where('user_id', $this->leader_id)->exists()) {
            $this->members()->attach($this->leader_id);
        }
    }

    protected function syncAssistantLeaderAsMember(): void
    {
        if ($this->assistant_leader_id && !$this->members()->where('user_id', $this->assistant_leader_id)->exists()) {
            $this->members()->attach($this->assistant_leader_id);
        }
    }
}
