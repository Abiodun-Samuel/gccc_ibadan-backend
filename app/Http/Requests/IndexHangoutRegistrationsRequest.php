<?php

namespace App\Http\Requests;

use App\Services\HangoutRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexHangoutRegistrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route-level admin guard handles authorization
    }

    public function rules(): array
    {
        return [
            'search'    => ['nullable', 'string', 'max:100'],
            'game'      => ['nullable', 'string', 'max:100'],
            'menu'      => ['nullable', 'string', 'max:100'], // a meal, or 'none'
            'gender'    => ['nullable', 'string', 'max:10'], // a gender, or 'none'
            'support'   => ['nullable', Rule::in(['with', 'without'])],
            'sort'      => ['nullable', Rule::in(HangoutRegistrationService::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }
}
