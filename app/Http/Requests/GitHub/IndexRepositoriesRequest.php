<?php

declare(strict_types=1);

namespace App\Http\Requests\GitHub;

use Illuminate\Foundation\Http\FormRequest;

final class IndexRepositoriesRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
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
