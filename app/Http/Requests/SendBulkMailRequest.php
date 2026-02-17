<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkMailRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_ids'      => ['required', 'array', 'min:1'],
            'user_ids.*'    => ['required', 'integer', 'exists:users,id'],
            'template_id'   => ['required', 'string'],
            'cc_recipients' => ['sometimes', 'array'],
            'cc_recipients.*.email' => ['required_with:cc_recipients', 'email'],
            'cc_recipients.*.name'  => ['sometimes', 'string'],
            'bcc_recipients' => ['sometimes', 'array'],
            'bcc_recipients.*.email' => ['required_with:bcc_recipients', 'email'],
            'bcc_recipients.*.name'  => ['sometimes', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'template_id.required' => 'Email template ID is required',
            'user_ids.required' => 'At least one user must be selected',
            'user_ids.*.exists' => 'One or more selected users do not exist',
        ];
    }
}
