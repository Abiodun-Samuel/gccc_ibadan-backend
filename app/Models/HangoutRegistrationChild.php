<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A child an adult brings to the hangout, on the adult's registration.
 */
class HangoutRegistrationChild extends Model
{
    protected $table = 'hangout_registration_children';

    protected $fillable = [
        'full_name',
        'gender',
        'game',
        'menu',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(HangoutRegistration::class, 'hangout_registration_id');
    }
}
