<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

final class WorkspacePolicy
{
    /**
     * Determine whether the user can view any workspaces.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can create workspaces.
     */
    public function create(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }

    /**
     * Determine whether the user can delete the workspace.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->isOwnerOf($workspace);
    }

    /**
     * Determine whether the user can manage members in the workspace.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageMembers() ?? false;
    }

    /**
     * Determine whether the user can invite members to the workspace.
     */
    public function invite(User $user, Workspace $workspace): bool
    {
        return $this->manageMembers($user, $workspace);
    }

    /**
     * Determine whether the user can transfer ownership of the workspace.
     */
    public function transferOwnership(User $user, Workspace $workspace): bool
    {
        return $user->isOwnerOf($workspace);
    }
}
