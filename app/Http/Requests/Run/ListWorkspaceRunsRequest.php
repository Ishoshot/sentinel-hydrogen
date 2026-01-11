<?php

declare(strict_types=1);

namespace App\Http\Requests\Run;

use App\Enums\RunStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListWorkspaceRunsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(RunStatus::values())],
            'repository_id' => ['sometimes', 'nullable', 'integer', 'exists:repositories,id'],
            'risk_level' => ['sometimes', 'nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'sort_by' => ['sometimes', Rule::in(['created_at', 'completed_at', 'findings_count'])],
            'sort_order' => ['sometimes', Rule::in(['asc', 'desc'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'The per page must not be greater than 100.',
            'status.in' => 'The selected status is invalid.',
            'risk_level.in' => 'The selected risk level is invalid.',
            'sort_by.in' => 'The selected sort field is invalid.',
            'sort_order.in' => 'The selected sort order is invalid.',
            'to_date.after_or_equal' => 'The to date must be after or equal to the from date.',
        ];
    }

    /**
     * Get validated pagination limit.
     */
    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }

    /**
     * Get validated sort field.
     */
    public function sortBy(): string
    {
        return $this->validated('sort_by', 'created_at');
    }

    /**
     * Get validated sort order.
     */
    public function sortOrder(): string
    {
        return $this->validated('sort_order', 'desc');
    }
}
