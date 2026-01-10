<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

final class RepositoryPolicy
{
    /**
     * Determine whether the user can view any repositories.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the repository.
     */
    public function view(User $user, Repository $repository): bool
    {
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return false;
        }

        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can update the repository settings.
     */
    public function update(User $user, Repository $repository): bool
    {
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }

    /**
     * Determine whether the user can sync repositories.
     */
    public function sync(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }
}
