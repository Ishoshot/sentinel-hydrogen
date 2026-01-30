<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AnalyticsQueryRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'group_by' => ['sometimes', 'string', Rule::in(['day', 'week', 'month'])],
        ];
    }

    /**
     * Get the number of days to query.
     */
    public function days(int $default = 30): int
    {
        $days = $this->validated('days');

        return is_numeric($days) ? (int) $days : $default;
    }

    /**
     * Get the limit for results.
     */
    public function limit(int $default = 10): int
    {
        $limit = $this->validated('limit');

        return is_numeric($limit) ? (int) $limit : $default;
    }

    /**
     * Get the group by period.
     */
    public function groupBy(string $default = 'day'): string
    {
        $groupBy = $this->validated('group_by');

        return is_string($groupBy) ? $groupBy : $default;
    }
}
