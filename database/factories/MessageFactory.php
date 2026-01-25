<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'read_at' => fake()->boolean(50) ? fake()->dateTimeBetween('-1 week', 'now') : null,
            'archived_by_sender' => false,
            'archived_by_recipient' => false,
            'deleted_by_sender' => false,
            'deleted_by_recipient' => false,
        ];
    }

    /**
     * Indicate that the message is unread.
     */
    public function unread(): static
    {
        return $this->state(fn(array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the message is read.
     */
    public function read(): static
    {
        return $this->state(fn(array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the message is archived by recipient.
     */
    public function archivedByRecipient(): static
    {
        return $this->state(fn(array $attributes) => [
            'archived_by_recipient' => true,
        ]);
    }

    /**
     * Indicate that the message is archived by sender.
     */
    public function archivedBySender(): static
    {
        return $this->state(fn(array $attributes) => [
            'archived_by_sender' => true,
        ]);
    }

    /**
     * Indicate that the message is deleted by recipient.
     */
    public function deletedByRecipient(): static
    {
        return $this->state(fn(array $attributes) => [
            'deleted_by_recipient' => true,
        ]);
    }

    /**
     * Indicate that the message is deleted by sender.
     */
    public function deletedBySender(): static
    {
        return $this->state(fn(array $attributes) => [
            'deleted_by_sender' => true,
        ]);
    }
}
