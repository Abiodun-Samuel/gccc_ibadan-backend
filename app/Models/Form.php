<?php

namespace App\Models;

use App\Enums\FormTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Form extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $fillable = [
        'type',
        'name',
        'phone_number',
        'is_completed',
        'wants_to_share_testimony',
        'content',
        'user_id'
    ];

    protected $casts = [
        'wants_to_share_testimony' => 'boolean',
        'is_completed' => 'boolean',
        'type' => FormTypeEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'first_name', 'last_name', 'email', 'gender', 'phone_number', 'avatar']);
    }
}
