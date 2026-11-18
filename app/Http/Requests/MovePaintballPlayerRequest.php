<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin: moving a paintball player into another group.
 */
class MovePaintballPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin routes only
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', 'exists:hangout_paintball_groups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_id.required' => 'Choose a group.',
            'group_id.exists'   => 'That group has been removed. Refresh to see the latest groups.',
        ];
    }
}
