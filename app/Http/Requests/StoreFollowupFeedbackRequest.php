<?php

namespace App\Http\Requests;

use App\Models\FirstTimer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFollowupFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Adjust based on your authorization logic
    }

    public function rules(): array
    {
        return [
            'followupable_type' => ['required', 'string', Rule::in([FirstTimer::class, User::class])],
            'followupable_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $type = $this->input('followupable_type');
                    if ($type && !$type::find($value)) {
                        $fail('The selected ' . class_basename($type) . ' does not exist.');
                    }
                },
            ],
            'type' => [
                'required',
                Rule::in([
                    'Pre-Service',
                    'Post-Service',
                    'Admin',
                    'Pastor',
                    'Unit-Leader',
                    'Others'
                ])
            ],
            'note' => ['required', 'string', 'min:10'],
            'service_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'followupable_type.required' => 'Please specify whether this is for a first timer or member.',
            'followupable_id.required' => 'Please select who this feedback is for.',
            'note.required' => 'Please provide feedback notes.',
            'note.min' => 'Feedback notes must be at least 10 characters.',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('subject_type')) {
            $typeMap = [
                'first-timers' => FirstTimer::class,
                'members' => User::class,
                'user' => User::class,
            ];

            $this->merge([
                'followupable_type' => $typeMap[strtolower($this->subject_type)] ?? $this->followupable_type,
            ]);
        }

        if ($this->has('subject_id') && !$this->has('followupable_id')) {
            $this->merge([
                'followupable_id' => $this->subject_id,
            ]);
        }
    }
}
