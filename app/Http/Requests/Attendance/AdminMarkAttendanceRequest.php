<?php

namespace App\Http\Requests\Attendance;

use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;

class AdminMarkAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(RoleEnum::ADMIN->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'attendance_date' => 'required|date|before_or_equal:today',
            'attendances' => 'required|array|min:1|max:500', // Limit bulk operations
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => 'required|in:present,absent',
            'attendances.*.mode' => 'nullable|required_if:attendances.*.status,present|in:online,onsite',
        ];
    }
    public function messages(): array
    {
        return [
            'service_id.required' => 'Service selection is required.',
            'attendance_date.required' => 'Attendance date is required.',
            'attendance_date.before_or_equal' => 'Attendance date cannot be in the future.',
            'attendances.required' => 'At least one attendance record is required.',
            'attendances.max' => 'Maximum 500 attendance records can be processed at once.',
            'attendances.*.user_id.required' => 'User ID is required for each attendance record.',
            'attendances.*.user_id.exists' => 'One or more selected users are invalid.',
            'attendances.*.status.required' => 'Status is required for each attendance record.',
            'attendances.*.mode.required_if' => 'Mode is required when status is present.',
        ];
    }
}
