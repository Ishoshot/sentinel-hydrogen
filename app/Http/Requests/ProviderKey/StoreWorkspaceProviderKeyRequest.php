<?php

declare(strict_types=1);

namespace App\Http\Requests\ProviderKey;

use App\Enums\AI\AiProvider;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Override;

final class StoreWorkspaceProviderKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        if (! $workspace instanceof Workspace) {
            return false;
        }

        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->roleInWorkspace($workspace)?->canManageSettings() ?? false;
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
    #[Override]
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
