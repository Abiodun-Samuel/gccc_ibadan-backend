<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreEventRegistrationRequest extends FormRequest
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
            'event'                     => ['required', 'string'],
            'selected_dates'             => ['array'],
            'num_days'                   => ['nullable', 'integer', 'min:1'],
            'nights'                     => ['required', 'integer', 'min:0'],
            'accommodation'             => ['boolean'],
            'feeding'                   => ['boolean'],
            'feeding_cost'              => ['nullable', 'numeric'],
            'transport_cost'            => ['nullable', 'numeric'],
            'couples'                   => ['boolean'],
            'couples_cost'              => ['nullable', 'numeric'],
            'total'                     => ['nullable', 'numeric'],
            'transportation'            => ['array'],
            'transportation.to'         => ['boolean'],
            'transportation.fro'        => ['boolean'],
            'interested_in_serving'     => ['nullable', 'boolean'],
            'integrated_into_a_unit'    => ['nullable', 'boolean'],
            'specify_unit'              => ['nullable', 'string'],
            'is_student'                => ['boolean'],
            'institution'               => ['nullable', 'string'],
        ];
    }
}
