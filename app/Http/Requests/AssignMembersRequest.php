<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class AssignMembersRequest extends FormRequest
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
            'member_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'member_ids.*' => [
                'required',
                'integer',
                'exists:users,id',
                'distinct',
            ],
            'followup_leader_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'followup_leader_ids.*' => [
                'required',
                'integer',
                'exists:users,id',
                'distinct',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'member_ids.required' => 'Please select at least one member',
            'member_ids.min' => 'Please select at least one member',
            'followup_leader_ids.required' => 'Please select at least one follow-up leader',
            'followup_leader_ids.min' => 'Please select at least one follow-up leader',
        ];
    }
}
