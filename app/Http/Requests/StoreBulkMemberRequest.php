<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin', 'leader']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'min:1', 'max:100'], // Limit to 100 members at once
            'members.*.first_name' => ['required', 'string', 'max:255'],
            'members.*.last_name' => ['required', 'string', 'max:255'],
            'members.*.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
            ],
            'members.*.gender' => ['required', 'string', Rule::in(['Male', 'Female'])],
            'members.*.phone_number' => [
                'required',
                'string',
                Rule::unique('users', 'phone_number')
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'members.required' => 'At least one member is required',
            'members.*.first_name.required' => 'First name is required for all members',
            'members.*.last_name.required' => 'Last name is required for all members',
            'members.*.email.required' => 'Email is required for all members',
            'members.*.email.email' => 'Please provide a valid email address',
            'members.*.email.unique' => 'This email is already registered',
            'members.*.gender.required' => 'Gender is required for all members',
            'members.*.gender.in' => 'Gender must be male, female, or other',
            'members.*.phone_number.required' => 'Phone number is required for all members',
            'members.*.phone_number.unique' => 'This phone number is already registered',
        ];
    }
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->input('members', []) as $index => $member) {
            $memberNumber = $index + 1;
            $attributes["members.{$index}.first_name"] = "member #{$memberNumber} first name";
            $attributes["members.{$index}.last_name"] = "member #{$memberNumber} last name";
            $attributes["members.{$index}.email"] = "member #{$memberNumber} email";
            $attributes["members.{$index}.gender"] = "member #{$memberNumber} gender";
            $attributes["members.{$index}.phone_number"] = "member #{$memberNumber} phone number";
        }

        return $attributes;
    }
}
