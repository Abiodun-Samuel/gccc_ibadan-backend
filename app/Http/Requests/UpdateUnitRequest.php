<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'assistant_leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'name.string' => 'The unit name must be a valid string.',
            'member_ids.array' => 'Member IDs must be provided as an array.',
            'member_ids.*.exists' => 'One or more member IDs are invalid.',
            'assistant_id.exists' => 'The selected assistant does not exist.',
            'leader_id.exists' => 'The selected leader does not exist.',
        ];
    }
}
