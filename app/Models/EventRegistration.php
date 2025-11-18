<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'selected_dates',
        'num_days',
        'nights',
        'accommodation',
        'feeding',
        'feeding_cost',
        'transport_cost',
        'couples',
        'couples_cost',
        'total',
        'transportation',
        'interested_in_serving',
        'integrated_into_a_unit',
        'specify_unit',
        'is_student',
        'institution',
    ];

    protected $casts = [
        'selected_dates' => 'array',
        'transportation' => 'array',
        'feeding' => 'boolean',
        'accommodation' => 'boolean',
        'couples' => 'boolean',
        'interested_in_serving' => 'boolean',
        'integrated_into_a_unit' => 'boolean',
        'is_student' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function transactions()
    {
        return $this->hasMany(EventTransaction::class, 'event_registration_id');
    }
}
