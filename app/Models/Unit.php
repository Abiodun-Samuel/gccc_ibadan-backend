<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name', 'leader_id', 'assistant_leader_id', 'assistant_leader_id_2'];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function assistantLeader()
    {
        return $this->belongsTo(User::class, 'assistant_leader_id');
    }

    public function assistantLeader2()
    {
        return $this->belongsTo(User::class, 'assistant_leader_id_2');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'unit_user')
            ->withTimestamps()
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.phone_number', 'users.gender');
    }
}
