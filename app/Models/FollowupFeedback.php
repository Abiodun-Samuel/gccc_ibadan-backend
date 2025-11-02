<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupFeedback extends Model
{
    protected $table = 'followup_feedbacks';
    protected $fillable = [
        'user_id',
        'created_by',
        'note',
        'type',
        'service_date'
    ];
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
