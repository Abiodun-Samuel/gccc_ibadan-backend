<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRedeemableItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'string', 'max:255'],
            'subtitle'        => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'image'           => ['nullable', 'string'],
            'points_required' => ['sometimes', 'integer', 'min:1'],
            'stock'           => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['boolean'],
            'category'        => ['nullable', 'string', 'max:100'],
        ];
    }
}
