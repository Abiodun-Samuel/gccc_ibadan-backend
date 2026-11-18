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
        $hangout = app(HangoutRegistrationService::class);

        return [
            'full_name'       => ['required', 'string', 'max:150'],
            'gender'          => ['required', 'string', Rule::in($hangout->genderNames())],
            'email'           => ['required', 'string', 'email', 'max:255'],
            'phone_number'    => ['required', 'string', 'max:25', self::PHONE],
            'whatsapp_number' => ['nullable', 'string', 'max:25', self::PHONE],
            // The adult's own game: not a children-only one.
            'game'            => ['required', 'string', Rule::in($hangout->adultGameNames())],
            'menu'            => ['required', 'string', Rule::in($hangout->menuNames())],
            // The phone number the registration was saved with, when changing it.
            'current_phone'   => ['nullable', 'string', 'max:25', self::PHONE],
            'support_amount'  => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000000'],
            // Children the adult is bringing. Sent, it replaces any saved; left out, they stay.
            'children'             => ['sometimes', 'array', 'max:' . $hangout->maxChildren()],
            'children.*'           => ['array:full_name,gender,game,menu'],
            'children.*.full_name' => ['required', 'string', 'max:150'],
            'children.*.gender'    => ['required', 'string', Rule::in($hangout->genderNames())],
            'children.*.game'      => ['required', 'string', Rule::in($hangout->gameNames())],
            'children.*.menu'      => ['required', 'string', Rule::in($hangout->menuNames())],
        ];
    }

    public function attributes(): array
    {
        return ['children.*.full_name' => "child's name"];
    }

    public function messages(): array
    {
        return [
            'full_name.required'    => 'Enter your full name.',
            'gender.required'       => 'Choose your gender.',
            'gender.in'             => 'Choose male or female.',
            'email.required'        => 'Enter your email address.',
            'email.email'           => 'Enter a valid email address.',
            'phone_number.required' => 'Enter your phone number.',
            'phone_number.regex'    => 'Enter a valid phone number.',
            'whatsapp_number.regex' => 'Enter a valid WhatsApp number.',
            'game.required'         => 'Choose the game you want to play.',
            'game.in'               => 'Choose one of the hangout games for yourself. Some games are for children only.',
            'menu.required'         => 'Choose your meal.',
            'menu.in'               => 'Choose one of the two meals.',
            'support_amount.numeric' => 'Enter the amount as a number, for example 5000.',
            'support_amount.decimal' => 'Use at most two decimal places.',
            'support_amount.gt'     => 'Enter an amount above zero, or leave it empty.',
            'support_amount.max'    => 'That amount is too large. Please contact the church office.',
            'children.max'                  => 'You can add up to :max children.',
            'children.*.full_name.required' => 'Enter the child’s name.',
            'children.*.gender.required'    => 'Choose the child’s gender.',
            'children.*.gender.in'          => 'Choose male or female.',
            'children.*.game.required'      => 'Choose the child’s game.',
            'children.*.game.in'            => 'Choose one of the hangout games.',
            'children.*.menu.required'      => 'Choose the child’s meal.',
            'children.*.menu.in'            => 'Choose one of the two meals.',
        ];
    }
}
