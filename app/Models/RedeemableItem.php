<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RedeemableItem extends Model
{

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'image',
        'points_required',
        'stock',
        'is_active',
        'category',
        'total_redeemed',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'points_required' => 'integer',
            'stock'           => 'integer',
            'total_redeemed'  => 'integer',
        ];
    }

    /*--------------------------------------------------------------
    | Accessors
    --------------------------------------------------------------*/

    public function getIsAvailableAttribute(): bool
    {
        return $this->is_active && ($this->stock === null || $this->stock > 0);
    }

    public function getStockLabelAttribute(): string
    {
        return $this->stock === null ? 'Unlimited' : (string) $this->stock;
    }

    /*--------------------------------------------------------------
    | Relationships
    --------------------------------------------------------------*/

    /** Users who have redeemed this item */
    public function redeemers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'redeemable_item_user')
            ->withPivot(['points_spent', 'redeemed_at'])
            ->orderByPivot('redeemed_at', 'desc');
    }
}
