<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finding your own registration: by the email or the phone number it was
 * saved with. Given both, both must match.
 */
class LookupHangoutRegistrationRequest extends FormRequest
{
    private const EITHER = 'Enter the email or phone number you registered with.';

    public function authorize(): bool
    {
        return true; // Public: members look up their own registration
    }

    public function rules(): array
    {
        return [
            'email'        => ['nullable', 'required_without:phone_number', 'string', 'email', 'max:255'],
            'phone_number' => [
                'nullable', 'required_without:email', 'string', 'max:25', 'regex:/^[0-9+\-\s()]{7,25}$/',
                // The whole number, so a few digits can't match someone else's.
                function (string $attribute, mixed $value, Closure $fail) {
                    if (strlen(preg_replace('/\D+/', '', (string) $value)) < 10) {
                        $fail('Enter your whole phone number, for example 0801 234 5678.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without'        => self::EITHER,
            'email.email'                   => 'Enter a valid email address.',
            'phone_number.required_without' => self::EITHER,
            'phone_number.regex'            => 'Enter a valid phone number.',
        ];
    }
}
