<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'subject',
        'body',
        'read_at',
        'archived_by_sender',
        'archived_by_recipient',
        'deleted_by_sender',
        'deleted_by_recipient',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'archived_by_sender' => 'boolean',
        'archived_by_recipient' => 'boolean',
        'deleted_by_sender' => 'boolean',
        'deleted_by_recipient' => 'boolean',
    ];

    /*--------------------------------------------------------------
    | Relationships
    --------------------------------------------------------------*/

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')
            ->select(['id', 'first_name', 'last_name', 'email', 'avatar', 'gender']);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id')
            ->select(['id', 'first_name', 'last_name', 'email', 'avatar', 'gender']);
    }

    /*--------------------------------------------------------------
    | Query Scopes
    --------------------------------------------------------------*/

    /**
     * Get messages for inbox (received messages)
     */
    public function scopeInbox(Builder $query, int $userId): Builder
    {
        return $query->where('recipient_id', $userId)
            ->where('deleted_by_recipient', false);
    }

    /**
     * Get sent messages
     */
    public function scopeSent(Builder $query, int $userId): Builder
    {
        return $query->where('sender_id', $userId)
            ->where('deleted_by_sender', false);
    }

    /**
     * Get unread messages
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Get read messages
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Get archived messages
     */
    public function scopeArchived(Builder $query, int $userId, string $type = 'inbox'): Builder
    {
        if ($type === 'inbox') {
            return $query->where('recipient_id', $userId)
                ->where('archived_by_recipient', true)
                ->where('deleted_by_recipient', false);
        }

        return $query->where('sender_id', $userId)
            ->where('archived_by_sender', true)
            ->where('deleted_by_sender', false);
    }

    /**
     * Get conversation between two users
     */
    public function scopeConversationBetween(Builder $query, int $userId1, int $userId2): Builder
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where(function ($subQ) use ($userId1, $userId2) {
                $subQ->where('sender_id', $userId1)
                    ->where('recipient_id', $userId2)
                    ->where('deleted_by_sender', false);
            })->orWhere(function ($subQ) use ($userId1, $userId2) {
                $subQ->where('sender_id', $userId2)
                    ->where('recipient_id', $userId1)
                    ->where('deleted_by_recipient', false);
            });
        })->orderBy('created_at', 'asc');
    }

    /*--------------------------------------------------------------
    | Helper Methods
    --------------------------------------------------------------*/

    /**
     * Mark message as read
     */
    public function markAsRead(): bool
    {
        if ($this->read_at === null) {
            return $this->update(['read_at' => now()]);
        }
        return true;
    }

    /**
     * Mark message as unread
     */
    public function markAsUnread(): bool
    {
        return $this->update(['read_at' => null]);
    }

    /**
     * Archive message
     */
    public function archive(int $userId): bool
    {
        if ($this->sender_id === $userId) {
            return $this->update(['archived_by_sender' => true]);
        }

        if ($this->recipient_id === $userId) {
            return $this->update(['archived_by_recipient' => true]);
        }

        return false;
    }

    /**
     * Unarchive message
     */
    public function unarchive(int $userId): bool
    {
        if ($this->sender_id === $userId) {
            return $this->update(['archived_by_sender' => false]);
        }

        if ($this->recipient_id === $userId) {
            return $this->update(['archived_by_recipient' => false]);
        }

        return false;
    }

    /**
     * Soft delete message for user
     */
    public function deleteForUser(int $userId): bool
    {
        if ($this->sender_id === $userId) {
            return $this->update(['deleted_by_sender' => true]);
        }

        if ($this->recipient_id === $userId) {
            return $this->update(['deleted_by_recipient' => true]);
        }

        return false;
    }

    /**
     * Check if message is deletable (deleted by both users)
     */
    public function isDeletable(): bool
    {
        return $this->deleted_by_sender && $this->deleted_by_recipient;
    }
}
