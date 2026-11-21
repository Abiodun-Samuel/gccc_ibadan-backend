<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMembersRequest extends FormRequest
{
    /** The routes' role:admin middleware already decides who may delete. */
    public function authorize(): bool
    {
        return true;
    }

    /** No "exists" rule: an id that's gone is reported as not found instead of failing the batch. */
    public function rules(): array
    {
        return [
            'memberIds'   => ['required', 'array', 'min:1', 'max:100'],
            'memberIds.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'memberIds.required'   => 'Choose at least one member to delete.',
            'memberIds.min'        => 'Choose at least one member to delete.',
            'memberIds.array'      => 'Choose at least one member to delete.',
            'memberIds.max'        => 'You can delete up to 100 members at a time.',
            'memberIds.*.integer'  => 'Each member must be given by their id.',
            'memberIds.*.distinct' => 'Each member can only be chosen once.',
        ];
    }
}
