<?php

declare(strict_types=1);

namespace App\Http\Requests\GitHub;

use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Override;

final class UpdateRepositorySettingsRequest extends FormRequest
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
            'auto_review_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'auto_review_enabled.boolean' => 'The auto review setting must be a boolean value.',
        ];
    }
}
