<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFirstTimersRequest extends FormRequest
{
    /** The route's role:admin middleware already decides who may delete. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'   => 'Choose at least one first timer to delete.',
            'ids.min'        => 'Choose at least one first timer to delete.',
            'ids.max'        => 'You can delete up to 100 first timers at a time.',
            'ids.array'      => 'Choose at least one first timer to delete.',
            'ids.*.integer'  => 'Each first timer must be given by their id.',
            'ids.*.distinct' => 'Each first timer can only be chosen once.',
        ];
    }
}
