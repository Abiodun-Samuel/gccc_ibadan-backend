<?php

namespace App\Http\Requests;

use App\Enums\FormTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
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
            'type' => ['required', Rule::in(FormTypeEnum::values())],
            'user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'content' => ['required', 'string', 'min:3'],
            'name' => ['required_if:type,testimony', 'nullable', 'string', 'max:255'],
            'phone_number' => ['required_if:type,testimony', 'nullable', 'string', 'max:20'],
            'wants_to_share_testimony' => ['required_if:type,testimony', 'nullable', 'boolean'],
        ];
    }
    public function messages(): array
    {
        return [
            'name.required_if' => 'The name field is required when the form type is testimony.',
            'phone_number.required_if' => 'The phone number field is required when the form type is testimony.',
            'wants_to_share_testimony.required_if' => 'You must specify if you want to share the testimony.',
        ];
    }
}
