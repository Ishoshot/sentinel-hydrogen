<?php

declare(strict_types=1);

namespace App\Http\Requests\ProviderKey;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProviderKeyRequest extends FormRequest
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
            'provider_model_id' => ['nullable', 'integer', 'exists:provider_models,id'],
        ];
    }

    /**
     * Get the provider model ID if provided.
     */
    public function providerModelId(): ?int
    {
        $id = $this->validated('provider_model_id');

        return is_numeric($id) ? (int) $id : null;
    }
}
