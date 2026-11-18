<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin: adding a paintball group (the name is optional: without one it
 * takes the next Bible name) or renaming one (the name is needed).
 */
class SavePaintballGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin routes only
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => [
                $group ? 'required' : 'nullable', 'string', 'max:80',
                Rule::unique('hangout_paintball_groups', 'name')->ignore($group),
            ],
            'reference' => ['nullable', 'string', 'max:60'],
            'motto'     => ['nullable', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give the group a name.',
            'name.unique'   => 'Another group already has that name.',
        ];
    }
}
