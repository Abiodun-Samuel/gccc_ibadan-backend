<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->formatted_date,
            'time' => $this->formatted_time,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location,
            'image' => $this->image ? url($this->image) : null,
            'status' => $this->status,
            'registration_link' => $this->registration_link,
            'registration_deadline' => $this->registration_deadline?->toDateTimeString(),
            'is_registration_open' => $this->isRegistrationOpen(),
            'audio_streaming_link' => $this->audio_streaming_link,
            'video_streaming_link' => $this->video_streaming_link,
            'has_streaming' => $this->hasStreaming(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
