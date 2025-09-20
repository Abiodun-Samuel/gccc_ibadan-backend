<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowUpStatus extends Model
{
    use HasFactory;
    public const DEFAULT_STATUS_ID = 6;
    protected $fillable = ['title', 'color', 'description'];

    public function firstTimers()
    {
        return $this->hasMany(FirstTimer::class, 'follow_up_status_id');
    }
}
