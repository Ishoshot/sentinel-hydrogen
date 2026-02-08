<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Briefing;

use Illuminate\Foundation\Http\FormRequest;

final class IndexBriefingsRequest extends FormRequest
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
            'active_only' => ['sometimes', 'boolean'],
            'system_only' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Check if only active briefings should be returned.
     */
    public function activeOnly(): bool
    {
        return $this->boolean('active_only');
    }

    /**
     * Check if only system briefings should be returned.
     */
    public function systemOnly(): bool
    {
        return $this->boolean('system_only');
    }

    /**
     * Get validated pagination limit.
     */
    public function perPage(): int
    {
        $perPage = $this->validated('per_page');

        return is_numeric($perPage) ? (int) $perPage : 15;
    }
}
