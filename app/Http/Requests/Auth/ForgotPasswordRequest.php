<?php

namespace App\Http\Requests\Auth;


use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use RateLimiter;
use Str;

class ForgotPasswordRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 3;
    private const DECAY_MINUTES = 15;
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->ensureIsNotRateLimited();
    }

    private function ensureIsNotRateLimited(): void
    {
        $key = $this->throttleKey();

        if (!RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            RateLimiter::hit($key, self::DECAY_MINUTES * 60);
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Too many password reset attempts. Please try again in %d minutes.',
                ceil($seconds / 60)
            ),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            'forgot-password|' . Str::lower($this->input('email')) . '|' . $this->ip()
        );
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'We could not find a user with that email address.',
        ];
    }
}
