<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_leader')
            ->withTimestamps();
    }

    public function leaders()
    {
        return $this->members()->wherePivot('is_leader', true);
    }
    public function assistantLeaders()
    {
        return $this->members()->wherePivot('is_asst_leader', true);
    }
}
