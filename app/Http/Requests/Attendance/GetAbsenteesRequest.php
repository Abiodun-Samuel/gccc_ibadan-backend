<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class GetAbsenteesRequest extends FormRequest
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
            'service_id' => 'required|exists:services,id',
            'service_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Service selection is required.',
            'service_id.exists' => 'Selected service is invalid.',
            'service_date.required' => 'Date is required.',
            'service_date.date' => 'Date must be a valid date format.',
        ];
    }
}
