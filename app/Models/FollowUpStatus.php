<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowUpStatus extends Model
{
    use HasFactory;
    public const NOT_CONTACTED_ID = 6;
    public const CONTACTED_ID = 5;
    protected $fillable = ['title', 'color', 'description'];

    public function firstTimers()
    {
        return $this->hasMany(User::class, 'follow_up_status_id');
    }
}
