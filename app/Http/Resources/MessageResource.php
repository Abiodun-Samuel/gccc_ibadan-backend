<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isSender = $this->sender_id === $user?->id;
        $isRecipient = $this->recipient_id === $user?->id;

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'read_at_human' => $this->read_at?->diffForHumans(),

            // Sender information
            'sender' => $this->whenLoaded('sender', fn() => [
                'id' => $this->sender->id,
                'full_name' => "{$this->sender->first_name} {$this->sender->last_name}",
                'initials' => generateInitials($this->sender->first_name, $this->sender->last_name),
                'email' => $this->sender->email,
                'avatar' => $this->sender->avatar,
                'gender' => $this->sender->gender,
            ]),

            // Recipient information
            'recipient' => $this->whenLoaded('recipient', fn() => [
                'id' => $this->recipient->id,
                'full_name' => "{$this->recipient->first_name} {$this->recipient->last_name}",
                'initials' => generateInitials($this->recipient->first_name, $this->recipient->last_name),
                'email' => $this->recipient->email,
                'avatar' => $this->recipient->avatar,
                'gender' => $this->recipient->gender,
            ]),

            // User-specific flags
            'is_sender' => $isSender,
            'is_recipient' => $isRecipient,
            'is_archived' => $isSender ? $this->archived_by_sender : $this->archived_by_recipient,

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
