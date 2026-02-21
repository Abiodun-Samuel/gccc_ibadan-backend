<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'wants_to_share_testimony' => $this->wants_to_share_testimony,
            'is_completed' => $this->is_completed,
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'gender' => $this->user->gender,
                'phone_number' => $this->user->phone_number,
                'avatar' => $this->user->avatar,
            ]),
            'content' => $this->content,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
