<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PicnicRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'games' => ['required', 'array', 'min:1'],
            'games.*' => ['required', 'string'],
            'support_amount' => ['nullable', 'numeric', 'min:0']
        ];
    }

    public function messages(): array
    {
        return [
            'games.required' => 'Please select at least one game to participate in.',
            'games.min' => 'Please select at least one game to participate in.',
            'support_amount.numeric' => 'Support amount must be a valid number.',
            'support_amount.min' => 'Support amount cannot be negative.'
        ];
    }
}
