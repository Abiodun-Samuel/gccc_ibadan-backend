<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientErrorLog extends Model
{
    protected $fillable = [
        'message',
        'stack',
        'component_stack',
        'error_id',
        'url',
        'user_agent',
    ];
}
