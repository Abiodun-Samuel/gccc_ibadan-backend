<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * The public welcome form (no login). Only what a visitor can tell us is accepted: fields only
     * staff should set (status, photos, follow-up details, staff notes, glory team, verification)
     * have no rule here, so they are left out of the validated data and never saved.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email')) && trim($this->input('email')) === '') {
            $this->merge(['email' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // Optional: a blank email is stored as a placeholder that can never receive mail.
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string'],
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\+\-\(\)]+$/'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'located_in_ibadan' => ['nullable', 'boolean'],
            'membership_interest' => ['nullable', Rule::in(['Yes', 'No', 'Maybe'])],
            'born_again' => ['nullable', 'string', 'max:255'],
            'whatsapp_interest' => ['nullable', 'boolean'],
            'is_student' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'how_did_you_learn' => ['nullable', 'string', 'max:255'],
            'invited_by' => ['nullable', 'string', 'max:255'],
            'service_experience' => ['nullable', 'string'],
            'prayer_point' => ['nullable', 'string'],

            'community' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city_or_state' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],

            'date_of_birth' => ['nullable', 'date'],
            'date_of_visit' => ['nullable', 'date'],
        ];
    }
}
