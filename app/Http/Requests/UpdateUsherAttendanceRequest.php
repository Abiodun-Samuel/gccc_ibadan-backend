<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsherAttendanceRequest extends FormRequest
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
            'male' => ['nullable', 'integer', 'min:0'],
            'female' => ['nullable', 'integer', 'min:0'],
            'total_attendance' => ['required', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'service_date' => ['nullable', 'date'],
            'service_day' => ['required', 'string'],
            'service_day_desc' => ['nullable', 'string'],
        ];
    }
}
