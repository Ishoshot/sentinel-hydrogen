<?php

declare(strict_types=1);

namespace App\Http\Requests\Activity;

use App\Enums\Workspace\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexActivitiesRequest extends FormRequest
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
            'type' => ['sometimes', 'nullable', 'string', Rule::in(ActivityType::values())],
            'category' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get the activity type filter.
     */
    public function type(): ?string
    {
        $type = $this->validated('type');

        return is_string($type) ? $type : null;
    }

    /**
     * Get the category filter.
     */
    public function category(): ?string
    {
        $category = $this->validated('category');

        return is_string($category) ? $category : null;
    }

    /**
     * Get validated pagination limit.
     */
    public function perPage(): int
    {
        $perPage = $this->validated('per_page');

        return is_numeric($perPage) ? min((int) $perPage, 100) : 20;
    }
}
