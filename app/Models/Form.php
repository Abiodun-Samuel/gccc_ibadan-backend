<?php

namespace App\Models;

use App\Enums\FormTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'phone_number',
        'wants_to_share_testimony',
        'content',
    ];

    protected $casts = [
        'wants_to_share_testimony' => 'boolean',
        'type' => FormTypeEnum::class,
    ];
}
