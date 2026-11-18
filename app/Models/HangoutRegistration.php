<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HangoutRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'gender',
        'email',
        'phone_number',
        'whatsapp_number',
        'game',
        'menu',
        'support_amount',
    ];

    protected $casts = [
        'support_amount' => 'decimal:2',
    ];

    /** The children this adult is bringing, in the order they were added. */
    public function children(): HasMany
    {
        return $this->hasMany(HangoutRegistrationChild::class)->orderBy('id');
    }

    /**
     * Scope: match name, email, phone or WhatsApp number, or a child's name.
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
            ->orWhere('whatsapp_number', 'like', $like)
            ->orWhereHas('children', fn(Builder $child) => $child->where('full_name', 'like', $like)));
    }

    /**
     * Scope: one meal, or 'none' for registrations that haven't picked one yet.
     */
    public function scopeMenu(Builder $query, ?string $menu): Builder
    {
        return self::chosen($query, 'menu', $menu);
    }

    /**
     * Scope: one gender, or 'none' for registrations made before it was asked.
     */
    public function scopeGender(Builder $query, ?string $gender): Builder
    {
        return self::chosen($query, 'gender', $gender);
    }

    private static function chosen(Builder $query, string $column, ?string $value): Builder
    {
        return match (true) {
            blank($value) => $query,
            $value === 'none' => $query->whereNull($column),
            default => $query->where($column, $value),
        };
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
