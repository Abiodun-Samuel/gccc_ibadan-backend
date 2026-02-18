<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'event_id'        => ['nullable', 'integer', 'exists:events,id'],
            'title'           => ['nullable', 'string', 'max:50'],
            'full_name'       => ['required', 'string', 'max:150'],
            'phone_number'    => ['required', 'string', 'max:25'],
            'whatsapp_number' => ['nullable', 'string', 'max:25'],
            'email'           => ['required', 'email', 'max:255', 'unique:registrations,email'],
            'attending'       => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'The user with this email has already been registered.',
        ];
    }
}
