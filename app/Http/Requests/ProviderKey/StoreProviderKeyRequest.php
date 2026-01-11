<?php

declare(strict_types=1);

namespace App\Http\Requests\ProviderKey;

use App\Enums\AiProvider;
use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProviderKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $repository = $this->route('repository');

        if (! $repository instanceof Repository) {
            return false;
        }

        $workspace = $repository->workspace;

        if ($workspace === null) {
            return false;
        }

        return $this->user()?->roleInWorkspace($workspace)?->canManageSettings() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:'.implode(',', AiProvider::values())],
            'key' => ['required', 'string', 'min:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Please select an AI provider.',
            'provider.in' => 'Invalid AI provider selected.',
            'key.required' => 'Please provide the API key.',
            'key.min' => 'API key must be at least 10 characters.',
        ];
    }
}
