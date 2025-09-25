<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateMembersRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'members' => 'required|array|min:1|max:100', // Limit bulk operations to 100
            'members.*.id' => 'required', // Email is required for identification
            'members.*.email' => 'required|string', // Email is required for identification
            'members.*.first_name' => 'nullable|string|max:255',
            'members.*.last_name' => 'nullable|string|max:255',
            'members.*.password' => 'nullable|string|min:6',
            'members.*.phone_number' => 'nullable|string|max:20',
            'members.*.gender' => 'nullable|in:Male,Female,Other',
            'members.*.avatar' => 'nullable|string|max:255',
            'members.*.address' => 'nullable|string|max:500',
            'members.*.community' => 'nullable|string|max:255',
            'members.*.worker' => 'nullable|string',
            'members.*.status' => ['nullable', Rule::in(array_column(Status::cases(), 'value'))],
            'members.*.date_of_birth' => 'nullable|date|before:today',
            'members.*.country' => 'nullable|string|max:255',
            'members.*.city_or_state' => 'nullable|string|max:255',
            'members.*.facebook' => 'nullable|url|max:255',
            'members.*.instagram' => 'nullable|url|max:255',
            'members.*.linkedin' => 'nullable|url|max:255',
            'members.*.twitter' => 'nullable|url|max:255',
            'members.*.unit_ids' => 'nullable|array',
            'members.*.unit_ids.*' => 'integer|exists:units,id',
        ];
    }


    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'members.required' => 'Members data is required',
            'members.array' => 'Members must be an array',
            'members.min' => 'At least one member is required',
            'members.max' => 'Maximum 100 members allowed per bulk operation',

            'members.*.email.required' => 'Email is required for member identification',
            // 'members.*.email.email' => 'Email must be a valid email address',
            'members.*.email.distinct' => 'Duplicate emails found in the request',

            'members.*.first_name.string' => 'First name must be a string',
            'members.*.first_name.max' => 'First name cannot exceed 255 characters',

            'members.*.last_name.string' => 'Last name must be a string',
            'members.*.last_name.max' => 'Last name cannot exceed 255 characters',

            'members.*.password.min' => 'Password must be at least 6 characters',

            'members.*.phone_number.string' => 'Phone number must be a string',
            'members.*.phone_number.max' => 'Phone number cannot exceed 20 characters',

            'members.*.gender.in' => 'Gender must be male, female, or other',

            'members.*.status.in' => 'Status must be active, inactive, or pending',

            'members.*.date_of_birth.date' => 'Date of birth must be a valid date',
            'members.*.date_of_birth.before' => 'Date of birth must be in the past',

            'members.*.facebook.url' => 'Facebook must be a valid URL',
            'members.*.instagram.url' => 'Instagram must be a valid URL',
            'members.*.linkedin.url' => 'LinkedIn must be a valid URL',
            'members.*.twitter.url' => 'Twitter must be a valid URL',

            'members.*.unit_ids.array' => 'Unit IDs must be an array',
            'members.*.unit_ids.*.integer' => 'Unit ID must be an integer',
            'members.*.unit_ids.*.exists' => 'One or more selected units do not exist',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        $attributes = [];

        if (is_array($this->members)) {
            foreach ($this->members as $index => $member) {
                $memberNumber = $index + 1;
                $email = $member['email'] ?? 'unknown';

                $attributes["members.{$index}.email"] = "member #{$memberNumber} ({$email}) email";
                $attributes["members.{$index}.first_name"] = "member #{$memberNumber} ({$email}) first name";
                $attributes["members.{$index}.last_name"] = "member #{$memberNumber} ({$email}) last name";
                $attributes["members.{$index}.password"] = "member #{$memberNumber} ({$email}) password";
                $attributes["members.{$index}.phone_number"] = "member #{$memberNumber} ({$email}) phone number";
                $attributes["members.{$index}.gender"] = "member #{$memberNumber} ({$email}) gender";
                $attributes["members.{$index}.status"] = "member #{$memberNumber} ({$email}) status";
                $attributes["members.{$index}.date_of_birth"] = "member #{$memberNumber} ({$email}) date of birth";
            }
        }

        return $attributes;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('members') && is_array($this->members)) {
            $members = [];

            foreach ($this->members as $index => $member) {

                // Trim string fields
                $stringFields = ['first_name', 'last_name', 'email', 'phone_number', 'community', 'address', 'country', 'city_or_state'];
                foreach ($stringFields as $field) {
                    if (isset($member[$field]) && is_string($member[$field])) {
                        $member[$field] = trim($member[$field]);

                        // Remove empty strings and convert to null for update operations
                        if ($member[$field] === '') {
                            $member[$field] = null;
                        }
                    }
                }

                // Remove null values for update operations (except email which is required for identification)
                $member = array_filter($member, function ($value, $key) {
                    return $key === 'email' || $value !== null;
                }, ARRAY_FILTER_USE_BOTH);

                $members[$index] = $member;
            }

            $this->merge(['members' => $members]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();

        // Group errors by member for better readability
        $groupedErrors = [];
        $memberErrors = 0;

        foreach ($errors as $field => $messages) {
            if (preg_match('/^members\.(\d+)\.(.+)$/', $field, $matches)) {
                $memberIndex = (int) $matches[1];
                $fieldName = $matches[2];
                $memberNumber = $memberIndex + 1;

                $email = $this->input("members.{$memberIndex}.email", 'unknown');
                $groupKey = "Member #{$memberNumber} ({$email})";

                if (!isset($groupedErrors[$groupKey])) {
                    $groupedErrors[$groupKey] = [];
                    $memberErrors++;
                }

                $groupedErrors[$groupKey][$fieldName] = $messages;
            } else {
                $groupedErrors['General'][$field] = $messages;
            }
        }

        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'status' => false,
                'message' => 'Validation failed for bulk update operation',
                'data' => $groupedErrors,
                'summary' => [
                    'total_members' => count($this->input('members', [])),
                    'total_errors' => count($errors),
                    'affected_members' => $memberErrors
                ]
            ], 422)
        );
    }
}
