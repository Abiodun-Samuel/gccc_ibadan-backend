<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFirstTimerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'last_name' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255',],
            'gender' => ['sometimes', 'string', Rule::in(['Male', 'Female', 'Other'])],
            'status' => ['string', Rule::in(array_column(Status::cases(), 'value'))],
            'located_in_ibadan' => ['sometimes', 'boolean'],
            'born_again' => ['sometimes', 'boolean'],
            'whatsapp_interest' => ['sometimes', 'boolean'],
            'membership_interest' => 'nullable|in:Yes,No,Maybe',
            'is_student' => ['sometimes', 'boolean'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'date_of_visit' => ['sometimes', 'nullable', 'date'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invited_by' => ['sometimes', 'string', 'max:255'],
            'service_experience' => ['sometimes', 'string', 'max:1000'],
            'prayer_point' => ['sometimes', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visitation_report' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'pastorate_call' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'friend_family' => ['sometimes', 'string', 'max:255'],
            'how_did_you_learn' => ['sometimes', 'string', 'max:255'],
            'follow_up_status_id' => ['sometimes', 'integer', 'exists:follow_up_statuses,id'],
            'assigned_to_member_id' => ['sometimes', 'integer', 'exists:users,id'],
            'avatar' => ['sometimes', 'regex:/^data:image\/(jpeg|png|jpg|gif|webp);base64,/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'follow_up_status_id.exists' => 'The selected follow-up status is invalid.',
            'assigned_to_member_id.exists' => 'The selected member does not exist.',
        ];
    }
    protected function prepareForValidation(): void
    {
        if ($this->has('assigned_to_member_id') && $this->assigned_to_member_id && !$this->has('assigned_at')) {
            $this->merge([
                'assigned_at' => now(),
            ]);
        }
    }
}
