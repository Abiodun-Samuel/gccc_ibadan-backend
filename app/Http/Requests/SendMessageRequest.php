<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_id' => [
                'required',
                'integer',
                'exists:users,id',
                'different:' . $this->user()->id, // Cannot send to yourself
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'recipient_id.required' => 'Please select a recipient',
            'recipient_id.exists' => 'The selected recipient does not exist',
            'recipient_id.different' => 'You cannot send a message to yourself',
            'body.required' => 'Message body is required',
            'body.max' => 'Message body cannot exceed 10,000 characters',
        ];
    }
}
