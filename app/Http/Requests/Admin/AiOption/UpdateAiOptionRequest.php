<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\AiOption;

use App\Enums\AI\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class UpdateAiOptionRequest extends FormRequest
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
        /** @var \App\Models\AiOption|null $aiOption */
        $aiOption = $this->route('ai_option');
        $aiOptionId = $aiOption?->id;
        $currentProvider = $aiOption?->provider?->value;

        return [
            'provider' => ['sometimes', 'string', Rule::enum(AiProvider::class)],
            'identifier' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('provider_models')->where(fn (\Illuminate\Database\Query\Builder $query) => $query->where('provider', $this->input('provider', $currentProvider)))->ignore($aiOptionId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'context_window_tokens' => ['nullable', 'integer', 'min:1'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'provider.enum' => 'The selected provider is invalid.',
            'identifier.unique' => 'This model identifier already exists for this provider.',
        ];
    }
}
