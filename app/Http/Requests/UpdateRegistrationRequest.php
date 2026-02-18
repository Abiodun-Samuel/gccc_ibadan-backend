<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route-level admin guard handles authorization
    }

    public function rules(): array
    {
        return [
            'event_id'        => ['nullable', 'integer', 'exists:events,id'],
            'title'           => ['nullable', 'string', 'max:50'],
            'full_name'       => ['sometimes', 'required', 'string', 'max:150'],
            'phone_number'    => ['sometimes', 'required', 'string', 'max:25'],
            'whatsapp_number' => ['nullable', 'string', 'max:25'],
            'email'           => ['sometimes', 'required', 'email', 'max:255'],
            'attending'       => ['sometimes', 'required', 'boolean'],
        ];
    }
}
