<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Services\Briefings\ValueObjects\BriefingGenerationStatusSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBriefingGenerationsRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Pagination
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Search
            'search' => ['sometimes', 'string', 'max:255'],

            // Filters
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::in(BriefingGenerationStatus::values())],
            'briefing_id' => ['sometimes', 'integer', 'exists:briefings,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],

            // Sorting
            'sort' => ['sometimes', 'string', Rule::in([
                'created_at',
                'started_at',
                'completed_at',
                'status',
            ])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Get the search query.
     */
    public function getSearch(): ?string
    {
        $search = $this->input('search');

        return is_string($search) ? $search : null;
    }

    /**
     * Get the status filters.
     */
    public function getStatuses(): ?BriefingGenerationStatusSet
    {
        $status = $this->input('status');

        if (! is_array($status)) {
            return null;
        }

        $filtered = array_filter($status, is_string(...));

        return BriefingGenerationStatusSet::fromStrings(array_values($filtered));
    }

    /**
     * Get the briefing ID filter.
     */
    public function getBriefingId(): ?int
    {
        return $this->input('briefing_id') ? (int) $this->input('briefing_id') : null;
    }

    /**
     * Get the date from filter.
     */
    public function getDateFrom(): ?string
    {
        $dateFrom = $this->input('date_from');

        return is_string($dateFrom) ? $dateFrom : null;
    }

    /**
     * Get the date to filter.
     */
    public function getDateTo(): ?string
    {
        $dateTo = $this->input('date_to');

        return is_string($dateTo) ? $dateTo : null;
    }

    /**
     * Get the sort column.
     */
    public function getSort(): string
    {
        $sort = $this->input('sort', 'created_at');

        return is_string($sort) ? $sort : 'created_at';
    }

    /**
     * Get the sort direction.
     */
    public function getDirection(): string
    {
        $direction = $this->input('direction', 'desc');

        return is_string($direction) ? $direction : 'desc';
    }

    /**
     * Get the per page value.
     */
    public function getPerPage(): int
    {
        return min((int) $this->input('per_page', 20), 100);
    }
}
