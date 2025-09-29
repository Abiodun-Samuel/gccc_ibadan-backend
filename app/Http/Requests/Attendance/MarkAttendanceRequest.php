<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'mode' => 'required|in:onsite,online',
            'status' => 'required|in:present,absent',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Service selection is required.',
            'service_id.exists' => 'Selected service is invalid.',
            'mode.required' => 'Attendance mode is required.',
            'mode.in' => 'Attendance mode must be either onsite or online.',
            'status.required' => 'Attendance status is required.',
            'status.in' => 'Attendance status must be either present or absent.',
        ];
    }
}
