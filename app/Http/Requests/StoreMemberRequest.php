<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole([RoleEnum::ADMIN->value]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'avatar' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:1000'],
            'community' => ['nullable', 'string', 'max:255'],
            'worker' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,pending'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'country' => ['nullable', 'string', 'max:255'],
            'city_or_state' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'url', 'max:255'],
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', 'exists:units,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email address is required.',
            'email.unique' => 'This email address is already taken.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'unit_ids.*.exists' => 'One or more selected units do not exist.',
            'leader_unit_ids.*.exists' => 'One or more selected leader units do not exist.',
        ];
    }
    /**
     * Get the validated data from the request with defaults.
     */
    public function getValidatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'string' => null,
            'status' => 'active',
            'unit_ids' => [],
            'leader_unit_ids' => [],
        ], $validated);
    }
}
