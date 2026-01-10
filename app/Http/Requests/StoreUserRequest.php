<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Status;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adjust with policies if needed
    }

    public function rules(): array
    {
        return [
            'followup_by_id' => ['nullable', 'exists:users,id'],
            'follow_up_status_id' => ['nullable', 'exists:follow_up_statuses,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'avatar' => ['nullable', 'string'],
            'secondary_avatar' => ['sometimes', 'nullable', 'string'],
            'phone_number' => ['required', 'string'],
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\+\-\(\)]+$/'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'status' => ['nullable', Rule::in(Status::values())],
            'located_in_ibadan' => ['nullable', 'boolean'],
            'membership_interest' => ['nullable', Rule::in(['Yes', 'No', 'Maybe'])],
            'born_again' => ['nullable', 'string', 'max:255'],
            'whatsapp_interest' => ['nullable', 'boolean'],
            'is_student' => ['nullable', 'boolean'],
            'is_glory_team_member' => ['nullable', 'sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'how_did_you_learn' => ['nullable', 'string', 'max:255'],
            'invited_by' => ['nullable', 'string', 'max:255'],
            'service_experience' => ['nullable', 'string'],
            'prayer_point' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],

            'community' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city_or_state' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'visitation_report' => ['nullable', 'string'],
            'pastorate_call' => ['nullable', 'string'],

            'week_ending' => ['nullable', 'date'],
            'assigned_at' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_visit' => ['nullable', 'date'],
            'email_verified_at' => ['nullable', 'date'],
        ];
    }
}
