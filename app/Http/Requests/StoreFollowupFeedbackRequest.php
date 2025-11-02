<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFollowupFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'created_by' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'type' => [
                'required',
                Rule::in([
                    'Pre-Service',
                    'Post-Service',
                    'Admin',
                    'Pastor',
                    'Unit-Leader',
                    'Others',
                ]),
            ],
            'note' => ['required', 'string', 'min:10'],
            'service_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select who this feedback is for.',
            'user_id.exists' => 'The selected user does not exist.',
            'note.required' => 'Please provide feedback notes.',
            'note.min' => 'Feedback notes must be at least 10 characters.',
        ];
    }
}
