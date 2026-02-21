<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                  => ['sometimes', 'required', 'string', 'max:255'],
            'description'            => ['sometimes', 'required', 'string'],
            'start_date'             => ['sometimes', 'required', 'date'],
            'end_date'               => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time'             => ['sometimes', 'required', 'date_format:H:i'],
            'end_time'               => ['nullable', 'date_format:H:i'],
            'location'               => ['sometimes', 'required', 'string', 'max:255'],
            'image'                  => ['nullable', 'string'],
            'registration_link'      => ['nullable', 'url'],
            'registration_deadline'  => ['nullable', 'date'],
            'audio_streaming_link'   => ['nullable', 'url'],
            'video_streaming_link'   => ['nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal'  => 'The end date must be on or after the start date.',
            'start_time.date_format'   => 'Start time must be in HH:MM format.',
            'end_time.date_format'     => 'End time must be in HH:MM format.',
            'image.max'                => 'The image must not exceed 5 MB.',
        ];
    }
}
