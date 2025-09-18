<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'attendance_date',
        'status',
        'mode',
    ];
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
