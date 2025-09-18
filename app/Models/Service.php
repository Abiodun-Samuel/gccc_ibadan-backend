<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'day_of_week',
        'start_time',
        'is_recurring',
        'service_date',
    ];
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
