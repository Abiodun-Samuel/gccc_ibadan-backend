<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTransaction extends Model
{
    protected $fillable = [
        'event_registration_id',
        'transaction_reference',
        'amount',
        'payment_method',
        'status',
        'note',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }
}
