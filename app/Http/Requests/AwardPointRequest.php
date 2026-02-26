<?php

namespace App\Http\Requests;

use App\Config\PointRewards;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AwardPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userId'        => ['required', 'integer', 'exists:users,id'],
            'action'        => ['required', 'string', Rule::in(array_keys(PointRewards::all()))],
        ];
    }

    public function messages(): array
    {
        return [
            'userId.exists'     => 'User not found.',
            'action.in'     => 'Invalid action type.',
        ];
    }
}
