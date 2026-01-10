<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Enums\TeamRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateInvitationRequest extends FormRequest
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

        return $this->user()?->roleInWorkspace($workspace)?->canManageMembers() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', 'string', Rule::in(TeamRole::assignableRoles())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please provide an email address.',
            'email.email' => 'Please provide a valid email address.',
            'role.required' => 'Please select a role for the invitee.',
            'role.in' => 'Invalid role selected.',
        ];
    }
}
