<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A paintball group at the hangout: a Bible name, its verse and motto. Its
 * players are the adults and children whose `paintball_group_id` is this
 * group's (see App\Services\PaintballGroupService).
 */
class HangoutPaintballGroup extends Model
{
    protected $fillable = [
        'name',
        'reference',
        'motto',
    ];
}
