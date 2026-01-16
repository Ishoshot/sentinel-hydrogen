<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateBriefingRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'parameters' => ['nullable', 'array'],
            'parameters.start_date' => ['nullable', 'date'],
            'parameters.end_date' => ['nullable', 'date', 'after_or_equal:parameters.start_date'],
            'parameters.repository_ids' => ['nullable', 'array'],
            'parameters.repository_ids.*' => ['integer', 'exists:repositories,id'],
        ];
    }
}
