<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFirstTimerRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:first_timers,email',
            'gender' => 'nullable|in:Male,Female,Other',
            'located_in_ibadan' => 'nullable|boolean',
            'interest' => 'nullable|in:Yes,No,Maybe',
            'born_again' => 'nullable|in:Yes,No,Uncertain',
            'whatsapp_interest' => 'nullable|boolean',
            'address' => 'nullable|string|max:500',
            'date_of_visit' => 'required|date',
            'date_of_birth' => 'nullable|date',
            'occupation' => 'nullable|string|max:255',
            'invited_by' => 'nullable|string|max:255',
            'service_experience' => 'nullable|string',
            'prayer_point' => 'nullable|string',
            'notes' => 'nullable|string',
            'visitation_report' => 'nullable|string',
            'status' => ['string', Rule::in(array_column(Status::cases(), 'value'))],
            'pastorate_call' => 'nullable|string',
            'friend_family' => 'nullable|string',
            'how_did_you_learn' => 'nullable|string',
        ];
    }
    public function messages(): array
    {
        return [
            'email.unique' => 'This email has already been registered as a first timer.',
        ];
    }
}
