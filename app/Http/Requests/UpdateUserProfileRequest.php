<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Adjust authorization logic if needed (e.g., admin or self-update)
        return true;
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'role' => ['nullable', 'string', 'max:100'],
            'worker' => ['nullable', Rule::in(['Yes', 'No'])],
            'address' => ['nullable', 'string', 'max:255'],
            'community' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city_or_state' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'url'],
            'instagram' => ['nullable', 'url'],
            'linkedin' => ['nullable', 'url'],
            'twitter' => ['nullable', 'url'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'education' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string'],
            'status' => ['string', Rule::in(array_column(Status::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already in use.',
            'date_of_birth.before' => 'Date of birth must be a valid date before today.',
        ];
    }
}
