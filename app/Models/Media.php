<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'video_id',
        'title',
        'description',
        'thumbnail_default',
        'thumbnail_medium',
        'thumbnail_high',
        'channel_id',
        'channel_title',
        'published_at',
    ];
    protected $casts = [
        'published_at' => 'datetime',
    ];
}
