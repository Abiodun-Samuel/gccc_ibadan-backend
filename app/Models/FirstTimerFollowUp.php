<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirstTimerFollowUp extends Model
{
    protected $fillable = ['first_timer_id', 'user_id', 'note', 'type', 'service_date'];
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }
    public function firstTimer()
    {
        return $this->belongsTo(FirstTimer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
