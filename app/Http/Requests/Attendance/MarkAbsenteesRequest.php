<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class MarkAbsenteesRequest extends FormRequest
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
            'service_id' => 'required|exists:services,id',
            'attendance_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Service selection is required.',
            'service_id.exists' => 'Selected service is invalid.',
            'attendance_date.required' => 'Date is required.',
        ];
    }

}
