<?php

namespace App\Http\Requests;

use App\Services\HangoutRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHangoutRegistrationRequest extends FormRequest
{
    private const PHONE = 'regex:/^[0-9+\-\s()]{7,25}$/';

    public function authorize(): bool
    {
        return true; // Public: anyone can register
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name'       => ['required', 'string', 'max:150'],
            'email'           => ['required', 'string', 'email', 'max:255'],
            'phone_number'    => ['required', 'string', 'max:25', self::PHONE],
            'whatsapp_number' => ['nullable', 'string', 'max:25', self::PHONE],
            'game'            => ['required', 'string', Rule::in(app(HangoutRegistrationService::class)->gameNames())],
            'support_amount'  => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'    => 'Enter your full name.',
            'email.required'        => 'Enter your email address.',
            'email.email'           => 'Enter a valid email address.',
            'phone_number.required' => 'Enter your phone number.',
            'phone_number.regex'    => 'Enter a valid phone number.',
            'whatsapp_number.regex' => 'Enter a valid WhatsApp number.',
            'game.required'         => 'Choose the game you want to play.',
            'game.in'               => 'Choose one of the hangout games.',
            'support_amount.numeric' => 'Enter the amount as a number, for example 5000.',
            'support_amount.decimal' => 'Use at most two decimal places.',
            'support_amount.gt'     => 'Enter an amount above zero, or leave it empty.',
            'support_amount.max'    => 'That amount is too large. Please contact the church office.',
        ];
    }
}
