<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Promotion;

use Illuminate\Foundation\Http\FormRequest;

final class IndexPromotionsRequest extends FormRequest
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
            'valid_only' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Check if only active promotions should be returned.
     */
    public function activeOnly(): bool
    {
        return $this->boolean('active_only');
    }

    /**
     * Check if only valid promotions should be returned.
     */
    public function validOnly(): bool
    {
        return $this->boolean('valid_only');
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
