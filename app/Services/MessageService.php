<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MessageService
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }
    /**
     * Get user's inbox messages with pagination
     */
    public function getInbox(User $user, int $perPage = 20) //: LengthAwarePaginator
    {
        return Message::inbox($user->id)
            ->with(['sender'])
            ->latest('created_at')
            ->get();
    }

    /**
     * Get user's sent messages with pagination
     */
    public function getSent(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Message::sent($user->id)
            ->with(['recipient'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Get user's archived messages
     */
    public function getArchived(User $user, string $type = 'inbox', int $perPage = 20): LengthAwarePaginator
    {
        $relation = $type === 'inbox' ? 'sender' : 'recipient';

        return Message::archived($user->id, $type)
            ->with([$relation])
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Get unread messages count
     */
    public function getUnreadCount(User $user): int
    {
        return Message::inbox($user->id)
            ->unread()
            ->count();
    }

    /**
     * Get conversation between two users
     */
    public function getConversation(User $user, int $otherUserId): Collection
    {
        return Message::conversationBetween($user->id, $otherUserId)
            ->with(['sender', 'recipient'])
            ->get();
    }

    /**
     * Send a new message
     */
    public function sendMessage(User $sender, array $data): Message
    {
        return DB::transaction(function () use ($sender, $data) {
            $message = Message::create([
                'sender_id' => $sender->id,
                'recipient_id' => $data['recipient_id'],
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'],
            ]);
            $this->sendMessageNotificationEmail($message, $sender);

            return $message;
        });
    }

    /**
     * Reply to a message
     */
    public function replyToMessage(User $sender, Message $originalMessage, array $data): Message
    {
        // Ensure sender is the recipient of the original message
        if ($originalMessage->recipient_id !== $sender->id) {
            throw new \InvalidArgumentException('You can only reply to messages sent to you');
        }

        return $this->sendMessage($sender, [
            'recipient_id' => $originalMessage->sender_id,
            'subject' => $data['subject'] ?? 'Re: ' . ($originalMessage->subject ?? 'No Subject'),
            'body' => $data['body'],
        ]);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Message $message, User $user): bool
    {
        // Only recipient can mark as read
        if ($message->recipient_id !== $user->id) {
            return false;
        }

        return $message->markAsRead();
    }

    /**
     * Mark message as unread
     */
    public function markAsUnread(Message $message, User $user): bool
    {
        // Only recipient can mark as unread
        if ($message->recipient_id !== $user->id) {
            return false;
        }

        return $message->markAsUnread();
    }

    /**
     * Mark multiple messages as read
     */
    public function markMultipleAsRead(array $messageIds, User $user): int
    {
        return Message::whereIn('id', $messageIds)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Archive message
     */
    public function archiveMessage(Message $message, User $user): bool
    {
        return $message->archive($user->id);
    }

    /**
     * Unarchive message
     */
    public function unarchiveMessage(Message $message, User $user): bool
    {
        return $message->unarchive($user->id);
    }

    /**
     * Delete message for user (soft delete)
     */
    public function deleteMessage(Message $message, User $user): bool
    {
        $deleted = $message->deleteForUser($user->id);

        // If both users deleted, permanently delete
        if ($deleted && $message->fresh()->isDeletable()) {
            $message->forceDelete();
        }

        return $deleted;
    }

    /**
     * Bulk delete messages
     */
    public function bulkDeleteMessages(array $messageIds, User $user): array
    {
        $deleted = 0;
        $permanentlyDeleted = 0;

        DB::transaction(function () use ($messageIds, $user, &$deleted, &$permanentlyDeleted) {
            $messages = Message::whereIn('id', $messageIds)
                ->where(function ($query) use ($user) {
                    $query->where('sender_id', $user->id)
                        ->orWhere('recipient_id', $user->id);
                })
                ->get();

            foreach ($messages as $message) {
                if ($this->deleteMessage($message, $user)) {
                    $deleted++;

                    // Check if it was permanently deleted
                    if (!Message::find($message->id)) {
                        $permanentlyDeleted++;
                    }
                }
            }
        });

        return [
            'deleted' => $deleted,
            'permanently_deleted' => $permanentlyDeleted,
        ];
    }

    /**
     * Get message by ID with authorization check
     */
    public function getMessageById(int $messageId, User $user): ?Message
    {
        return Message::with(['sender', 'recipient'])
            ->where('id', $messageId)
            ->where(function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)
                        ->where('deleted_by_sender', false);
                })->orWhere(function ($q) use ($user) {
                    $q->where('recipient_id', $user->id)
                        ->where('deleted_by_recipient', false);
                });
            })
            ->first();
    }

    /**
     * Get users with recent conversations
     */
    public function getRecentConversations(User $user, int $limit = 10): Collection
    {
        $sentMessages = DB::table('messages')
            ->select('recipient_id as user_id', DB::raw('MAX(created_at) as last_message_at'))
            ->where('sender_id', $user->id)
            ->where('deleted_by_sender', false)
            ->groupBy('recipient_id');

        $receivedMessages = DB::table('messages')
            ->select('sender_id as user_id', DB::raw('MAX(created_at) as last_message_at'))
            ->where('recipient_id', $user->id)
            ->where('deleted_by_recipient', false)
            ->groupBy('sender_id');

        $conversations = DB::table(DB::raw("({$sentMessages->toSql()} UNION {$receivedMessages->toSql()}) as conversations"))
            ->mergeBindings($sentMessages)
            ->mergeBindings($receivedMessages)
            ->select('user_id', DB::raw('MAX(last_message_at) as last_message_at'))
            ->groupBy('user_id')
            ->orderBy('last_message_at', 'desc')
            ->limit($limit)
            ->get();

        $userIds = $conversations->pluck('user_id');

        return User::whereIn('id', $userIds)
            ->select(['id', 'first_name', 'last_name', 'email', 'avatar', 'gender'])
            ->get()
            ->sortBy(function ($user) use ($conversations) {
                $conversation = $conversations->firstWhere('user_id', $user->id);
                return $conversation ? -strtotime($conversation->last_message_at) : 0;
            })
            ->values();
    }

    /**
     * Search messages
     */
    public function searchMessages(User $user, string $query, string $type = 'inbox', int $perPage = 20) //: LengthAwarePaginator
    {
        $baseQuery = Message::query();

        if ($type === 'inbox') {
            $baseQuery->inbox($user->id)->with(['sender']);
        } else {
            $baseQuery->sent($user->id)->with(['recipient']);
        }

        return $baseQuery->where(function ($q) use ($query) {
            $q->where('subject', 'LIKE', "%{$query}%")
                ->orWhere('body', 'LIKE', "%{$query}%");
        })
            ->latest('created_at')
            ->get($perPage);
    }

    protected function sendMessageNotificationEmail(Message $message, User $sender): void
    {
        try {
            // Get recipient
            $recipient = User::find($message->recipient_id);

            // Validate recipient exists and has email
            if (!$recipient || !$recipient->email) {
                return;
            }

            // Prepare sender name (fallback to email if name not available)
            $senderName = $this->formatFullName($sender);

            // Prepare recipient name (fallback to email if name not available)
            $recipientName = $this->formatFullName($recipient);

            // Prepare recipient data for email
            $recipients = [
                [
                    'email' => $recipient->email,
                    'name' => $recipientName,
                ]
            ];

            // Send email notification via MailService
            $this->mailService->sendNewMessageNotificationEmail(
                recipients: $recipients,
                senderName: $senderName
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * Format user's full name or fallback to email
     *
     * @param User $user
     * @return string
     */
    private function formatFullName(User $user): string
    {
        $fullName = trim("{$user->first_name} {$user->last_name}");

        // If name is empty, use email or fallback
        if (empty($fullName)) {
            return $user->email ?? 'A Church Member';
        }

        return $fullName;
    }
}
