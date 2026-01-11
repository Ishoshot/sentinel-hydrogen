<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;

final class InvitationPolicy
{
    /**
     * Determine whether the user can view any invitations.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the invitation.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        $workspace = $invitation->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageMembers() ?? false;
    }

    /**
     * Determine whether the user can create invitations.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageMembers() ?? false;
    }

    /**
     * Determine whether the user can delete the invitation.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        $workspace = $invitation->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageMembers() ?? false;
    }
}
