<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\AiOption;

use App\Enums\AI\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAiOptionsRequest extends FormRequest
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
            'provider' => ['sometimes', 'nullable', 'string', Rule::enum(AiProvider::class)],
            'active_only' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get the provider filter.
     */
    public function provider(): ?AiProvider
    {
        $provider = $this->validated('provider');

        return is_string($provider) ? AiProvider::tryFrom($provider) : null;
    }

    /**
     * Check if only active options should be returned.
     */
    public function activeOnly(): bool
    {
        return $this->boolean('active_only');
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
