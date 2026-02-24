<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsherAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin', 'leader', 'member']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_day' => ['required', 'string'],
            'service_day_desc' => ['nullable', 'string'],
            'male' => ['required', 'integer', 'min:0'],
            'total_attendance' => ['required', 'integer', 'min:0'],
            'female' => ['required', 'integer', 'min:0'],
            'children' => ['required', 'integer', 'min:0'],
            'service_date' => ['required', 'date'],
        ];
    }
}
