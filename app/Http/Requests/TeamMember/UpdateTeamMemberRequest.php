<?php

declare(strict_types=1);

namespace App\Http\Requests\TeamMember;

use App\Enums\Workspace\TeamRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class UpdateTeamMemberRequest extends FormRequest
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

        return $user->roleInWorkspace($workspace)?->canManageMembers() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(TeamRole::assignableRoles())],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'role.required' => 'Please select a role.',
            'role.in' => 'Invalid role selected.',
        ];
    }
}
