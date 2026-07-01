<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    protected $fillable = [
        'event_id',
        'title',
        'full_name',
        'phone_number',
        'whatsapp_number',
        'email',
        'attending',
        'needs_accommodation',
        'travelling_with_us',
        'needs_transport_fare_help',
        'travel_date',
    ];

    protected $casts = [
        'attending'                 => 'boolean',
        'needs_accommodation'       => 'boolean',
        'travelling_with_us'        => 'boolean',
        'needs_transport_fare_help' => 'boolean',
        'travel_date'               => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
