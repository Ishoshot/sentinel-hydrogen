<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use App\Enums\BriefingGenerationStatus;
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
        return $this->input('search');
    }

    /**
     * Get the status filters.
     *
     * @return list<string>|null
     */
    public function getStatuses(): ?array
    {
        return $this->input('status');
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
        return $this->input('date_from');
    }

    /**
     * Get the date to filter.
     */
    public function getDateTo(): ?string
    {
        return $this->input('date_to');
    }

    /**
     * Get the sort column.
     */
    public function getSort(): string
    {
        return $this->input('sort', 'created_at');
    }

    /**
     * Get the sort direction.
     */
    public function getDirection(): string
    {
        return $this->input('direction', 'desc');
    }

    /**
     * Get the per page value.
     */
    public function getPerPage(): int
    {
        return min((int) $this->input('per_page', 20), 100);
    }
}
