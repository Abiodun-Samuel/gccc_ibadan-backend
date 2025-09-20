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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:first_timers,email,' . $this->route('first_timer'),
            'gender' => 'nullable|in:Male,Female,Other',
            'located_in_ibadan' => 'nullable|boolean',
            'interest' => 'nullable|in:Yes,No,Maybe',
            'born_again' => 'nullable|in:Yes,No,Uncertain',
            'whatsapp_interest' => 'nullable|boolean',
            'address' => 'nullable|string|max:500',
            'date_of_visit' => 'sometimes|required|date',
            'date_of_birth' => 'nullable|date',
            'occupation' => 'nullable|string|max:255',
            'invited_by' => 'nullable|string|max:255',
            'service_experience' => 'nullable|string',
            'prayer_point' => 'nullable|string',
            'notes' => 'nullable|string',
            'week_ending' => 'nullable|date',
            'visitation_report' => 'nullable|string',
            'pastorate_call' => 'nullable|string',
            'follow_up_status_id' => 'nullable|exists:follow_up_statuses,id',
            'assigned_to_member_id' => 'nullable|exists:users,id',
            'assigned_at' => 'nullable|date',
            'status' => ['string', Rule::in(array_column(Status::cases(), 'value'))],
            'friend_family' => 'nullable|string',
            'how_did_you_learn' => 'nullable|string',
        ];
    }
}
