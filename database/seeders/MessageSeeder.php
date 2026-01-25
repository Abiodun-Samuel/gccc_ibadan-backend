<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        // Get all users
        $users = User::all();

        if ($users->count() < 2) {
            $this->command->info('Need at least 2 users. Creating users first...');
            $users = User::factory()->count(10)->create();
        }

        // Create messages between random users
        for ($i = 0; $i < 50; $i++) {
            $sender = $users->random();
            $recipient = $users->where('id', '!=', $sender->id)->random();

            Message::factory()->create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
            ]);
        }

        $this->command->info('50 messages created successfully!');
    }
}
