<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'community' => 'nullable|string|max:255',
            'avatar' => 'nullable|string',
            'status' => 'nullable|string',
            'worker' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'country' => 'nullable|string|max:255',
            'city_or_state' => 'nullable|string|max:255',
            'facebook' => 'nullable|url',
            'instagram' => 'nullable|url',
            'linkedin' => 'nullable|url',
            'twitter' => 'nullable|url',
            'password' => 'nullable|string|min:8|confirmed',
            'unit_ids' => 'array',
            'unit_ids.*' => 'exists:units,id',
            'leader_unit_ids' => 'array',
            'leader_unit_ids.*' => 'exists:units,id',
        ];
    }
}
