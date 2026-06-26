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
            // Either user_ids or emails must be provided (each requires the other to be absent/empty).
            'user_ids'      => ['required_without:emails', 'array', 'min:1'],
            'use_merge_info' => ['sometimes', 'boolean'],
            'user_ids.*'    => ['required', 'integer', 'exists:users,id'],
            // Alternative: a plain list of email-address strings.
            'emails'        => ['required_without:user_ids', 'array', 'min:1'],
            'emails.*'      => ['required', 'email'],
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
            'user_ids.required_without' => 'Either user_ids or emails must be provided',
            'user_ids.*.exists' => 'One or more selected users do not exist',
            'emails.required_without' => 'Either emails or user_ids must be provided',
            'emails.*.email' => 'One or more email addresses are invalid',
        ];
    }
}
