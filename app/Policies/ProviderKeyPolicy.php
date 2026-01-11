<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

final class ProviderKeyPolicy
{
    /**
     * Determine whether the user can view any provider keys for a workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the provider key.
     */
    public function view(User $user, ProviderKey $providerKey): bool
    {
        $workspace = $providerKey->workspace;

        if ($workspace === null) {
            return false;
        }

        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can create provider keys for a repository.
     */
    public function create(User $user, Repository $repository): bool
    {
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }

    /**
     * Determine whether the user can delete the provider key.
     */
    public function delete(User $user, ProviderKey $providerKey): bool
    {
        $workspace = $providerKey->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }
}
