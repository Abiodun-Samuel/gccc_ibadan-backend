<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyMessageRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService
    ) {}

    /**
     * Get inbox messages
     */
    public function inbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->messageService->getInbox(
            $request->user(),
            $validated['per_page'] ?? 20
        );

        return $this->successResponse(
            MessageResource::collection($messages),
            'Inbox messages retrieved successfully'
        );
    }

    /**
     * Get sent messages
     */
    public function sent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->messageService->getSent(
            $request->user(),
            $validated['per_page'] ?? 20
        );

        return $this->paginatedResponse(
            MessageResource::collection($messages),
            'Sent messages retrieved successfully'
        );
    }

    /**
     * Get archived messages
     */
    public function archived(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:inbox,sent'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->messageService->getArchived(
            $request->user(),
            $validated['type'] ?? 'inbox',
            $validated['per_page'] ?? 20
        );

        return $this->paginatedResponse(
            MessageResource::collection($messages),
            'Archived messages retrieved successfully'
        );
    }

    /**
     * Get unread messages count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->messageService->getUnreadCount($request->user());

        return $this->successResponse(
            ['unread_count' => $count],
            'Unread count retrieved successfully'
        );
    }

    /**
     * Get conversation with a specific user
     */
    public function conversation(Request $request, int $userId): JsonResponse
    {
        $messages = $this->messageService->getConversation(
            $request->user(),
            $userId
        );

        return $this->successResponse(
            MessageResource::collection($messages),
            'Conversation retrieved successfully'
        );
    }

    /**
     * Get recent conversations
     */
    public function recentConversations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $conversations = $this->messageService->getRecentConversations(
            $request->user(),
            $validated['limit'] ?? 10
        );

        return $this->successResponse(
            $conversations,
            'Recent conversations retrieved successfully'
        );
    }

    /**
     * Send a new message
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        try {
            $message = $this->messageService->sendMessage(
                $request->user(),
                $request->validated()
            );

            // Load relationships for response
            $message->load(['sender', 'recipient']);

            return $this->successResponse(
                new MessageResource($message),
                'Message sent successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to send message: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Reply to a message
     */
    public function reply(ReplyMessageRequest $request, Message $message): JsonResponse
    {
        try {
            // Authorization check
            if ($message->recipient_id !== $request->user()->id) {
                return $this->errorResponse(
                    'You can only reply to messages sent to you',
                    Response::HTTP_FORBIDDEN
                );
            }

            $reply = $this->messageService->replyToMessage(
                $request->user(),
                $message,
                $request->validated()
            );

            // Load relationships for response
            $reply->load(['sender', 'recipient']);

            return $this->successResponse(
                new MessageResource($reply),
                'Reply sent successfully',
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_FORBIDDEN
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to send reply: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get a specific message
     */
    public function show(Request $request, int $messageId): JsonResponse
    {
        $message = $this->messageService->getMessageById($messageId, $request->user());

        if (!$message) {
            return $this->errorResponse(
                'Message not found or access denied',
                Response::HTTP_NOT_FOUND
            );
        }

        // Auto-mark as read if user is recipient
        if ($message->recipient_id === $request->user()->id && !$message->read_at) {
            $this->messageService->markAsRead($message, $request->user());
            $message->refresh();
        }

        return $this->successResponse(
            new MessageResource($message),
            'Message retrieved successfully'
        );
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        $success = $this->messageService->markAsRead($message, $request->user());

        if (!$success) {
            return $this->errorResponse(
                'You can only mark your received messages as read',
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->successResponse(
            new MessageResource($message->fresh(['sender', 'recipient'])),
            'Message marked as read'
        );
    }

    /**
     * Mark message as unread
     */
    public function markAsUnread(Request $request, Message $message): JsonResponse
    {
        $success = $this->messageService->markAsUnread($message, $request->user());

        if (!$success) {
            return $this->errorResponse(
                'You can only mark your received messages as unread',
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->successResponse(
            new MessageResource($message->fresh(['sender', 'recipient'])),
            'Message marked as unread'
        );
    }

    /**
     * Mark multiple messages as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => ['required', 'integer', 'exists:messages,id'],
        ]);

        $count = $this->messageService->markMultipleAsRead(
            $validated['message_ids'],
            $request->user()
        );

        return $this->successResponse(
            ['marked_count' => $count],
            "{$count} messages marked as read"
        );
    }

    /**
     * Archive message
     */
    public function archive(Request $request, Message $message): JsonResponse
    {
        $success = $this->messageService->archiveMessage($message, $request->user());

        if (!$success) {
            return $this->errorResponse(
                'Unable to archive message',
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->successResponse(
            new MessageResource($message->fresh(['sender', 'recipient'])),
            'Message archived successfully'
        );
    }

    /**
     * Unarchive message
     */
    public function unarchive(Request $request, Message $message): JsonResponse
    {
        $success = $this->messageService->unarchiveMessage($message, $request->user());

        if (!$success) {
            return $this->errorResponse(
                'Unable to unarchive message',
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->successResponse(
            new MessageResource($message->fresh(['sender', 'recipient'])),
            'Message unarchived successfully'
        );
    }

    /**
     * Delete message (soft delete for user)
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        $success = $this->messageService->deleteMessage($message, $request->user());

        if (!$success) {
            return $this->errorResponse(
                'Unable to delete message',
                Response::HTTP_FORBIDDEN
            );
        }

        return $this->successResponse(
            null,
            'Message deleted successfully',
            Response::HTTP_NO_CONTENT
        );
    }

    /**
     * Bulk delete messages
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1', 'max:100'],
            'message_ids.*' => ['required', 'integer'],
        ]);

        $result = $this->messageService->bulkDeleteMessages(
            $validated['message_ids'],
            $request->user()
        );

        return $this->successResponse(
            $result,
            "{$result['deleted']} messages deleted successfully"
        );
    }

    /**
     * Search messages
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1'],
            'type' => ['nullable', 'string', 'in:inbox,sent'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->messageService->searchMessages(
            $request->user(),
            $validated['query'],
            $validated['type'] ?? 'inbox',
            $validated['per_page'] ?? 20
        );

        return $this->paginatedResponse(
            MessageResource::collection($messages),
            'Search results retrieved successfully'
        );
    }
}
