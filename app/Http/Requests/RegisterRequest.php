<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'unique:users,phone_number'],
            'email' => ['required', 'email', 'unique:users,email'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'role' => ['nullable', 'string', Rule::in(array_column(RoleEnum::cases(), 'value'))],
        ];
    }
    public function messages(): array
    {
        return [
            'role.in' => 'The selected role is invalid. Valid roles are: ' .
                implode(', ', array_column(RoleEnum::cases(), 'value')),
        ];
    }
}
