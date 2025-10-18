<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($this->member->id)],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'role' => ['nullable', 'string', 'max:100'],
            'worker' => ['nullable', Rule::in(['Yes', 'No'])],
            'avatar' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Status::values())],
            'address' => ['nullable', 'string', 'max:255'],
            'community' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'city_or_state' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'attendance_badge' => ['nullable', 'integer', 'min:0'],
            'last_badge_month' => ['nullable', 'integer', 'between:1,12'],
            'last_badge_year' => ['nullable', 'integer', 'min:2000', 'max:' . now()->year],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:user.id'],
            'assigned_at' => ['nullable', 'date'],
        ];
    }
}
