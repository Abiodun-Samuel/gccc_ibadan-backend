<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female'])],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city_or_state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education' => ['sometimes', 'nullable', 'string', 'max:255'],
            'field_of_study' => ['sometimes', 'nullable', 'string', 'max:255'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'community' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(array_column(Status::cases(), 'value'))],
            'worker' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'url', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Date of birth must be in the past',
            'gender.in' => 'Gender must be either male, female, or other',
        ];
    }
}
