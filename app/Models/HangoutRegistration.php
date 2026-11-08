<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HangoutRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone_number',
        'whatsapp_number',
        'game',
        'support_amount',
    ];

    protected $casts = [
        'support_amount' => 'decimal:2',
    ];

    /**
     * Scope: match name, email, phone or WhatsApp number.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)) . '%';

        return $query->where(fn(Builder $q) => $q
            ->where('full_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone_number', 'like', $like)
            ->orWhere('whatsapp_number', 'like', $like));
    }

    /**
     * Scope: registrations that did ('with') or didn't ('without') pledge support.
     */
    public function scopeSupport(Builder $query, ?string $support): Builder
    {
        return match ($support) {
            'with' => $query->whereNotNull('support_amount'),
            'without' => $query->whereNull('support_amount'),
            default => $query,
        };
    }
}
