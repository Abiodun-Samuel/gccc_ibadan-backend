<?php

namespace App\Http\Requests;

use App\Support\FirstTimerStaff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The first-timers list carries phone numbers and emails, so only the people whose
 * screens show it may load it: admins, and members of the Follow Up Unit.
 */
class ListFirstTimersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return FirstTimerStaff::allows($this->user());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Only admins and the follow-up unit can see the first timers list.');
    }

    /**
     * Only the visit range and the year are validated. The older filters are passed on
     * as before (Laravel's integer rule would reject a month sent as "09").
     */
    public function rules(): array
    {
        return [
            'visit_from'         => ['nullable', 'date_format:Y-m-d'],
            'visit_to'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:visit_from'],
            'date_year_of_visit' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'visit_from.date_format'     => 'The start of the range must be a date like 2025-09-01.',
            'visit_to.date_format'       => 'The end of the range must be a date like 2026-02-28.',
            'visit_to.after_or_equal'    => "The end month can't be before the start month.",
            'date_year_of_visit.integer' => 'Choose a year for the visit month.',
            'date_year_of_visit.between' => 'Choose a year for the visit month.',
        ];
    }
}
