<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\AiOption;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class StoreAiOptionRequest extends FormRequest
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
            'provider' => ['required', 'string', Rule::enum(AiProvider::class)],
            'identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique('provider_models')->where(function ($query) {
                    return $query->where('provider', $this->input('provider'));
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
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
            'provider.required' => 'The AI provider is required.',
            'provider.enum' => 'The selected provider is invalid.',
            'identifier.required' => 'The model identifier is required.',
            'identifier.unique' => 'This model identifier already exists for this provider.',
            'name.required' => 'The model name is required.',
        ];
    }
}
