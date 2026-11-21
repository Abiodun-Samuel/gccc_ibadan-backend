<?php

namespace App\Http\Requests;

use App\Support\FirstTimerStaff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/** Adding a first timer from the first-timers table: only the details that are really needed. */
class QuickAddFirstTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return FirstTimerStaff::allows($this->user());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Only admins and the follow-up unit can add first timers.');
    }

    /** A blank email is treated as none, so the server stores its placeholder. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email')) && trim($this->input('email')) === '') {
            $this->merge(['email' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'phone_number'  => ['required', 'string', 'max:20', 'regex:/^[\d\s\+\-\(\)]+$/'],
            'gender'        => ['required', 'in:Male,Female'],
            'date_of_visit' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email'         => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'           => 'Enter the first name.',
            'last_name.required'            => 'Enter the last name.',
            'phone_number.required'         => 'Enter a phone number.',
            'phone_number.regex'            => 'Use only digits, spaces, +, - and brackets in the phone number.',
            'phone_number.max'              => 'The phone number is too long.',
            'gender.required'               => 'Choose Male or Female.',
            'gender.in'                     => 'Choose Male or Female.',
            'date_of_visit.required'        => 'Enter the date they visited.',
            'date_of_visit.date_format'     => 'Enter the visit date as a date, like 2026-11-15.',
            'date_of_visit.before_or_equal' => "The visit date can't be in the future.",
            'email.email'                   => 'Enter a valid email, or leave it blank.',
            'email.unique'                  => 'Someone already uses this email. Leave it blank or use another.',
        ];
    }
}
