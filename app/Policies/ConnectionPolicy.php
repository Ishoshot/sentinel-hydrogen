<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;

final class ConnectionPolicy
{
    /**
     * Determine whether the user can view connections.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the connection.
     */
    public function view(User $user, Connection $connection): bool
    {
        $workspace = $connection->workspace;

        if ($workspace === null) {
            return false;
        }

        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can create a connection.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }

    /**
     * Determine whether the user can delete the connection.
     */
    public function delete(User $user, Connection $connection): bool
    {
        $workspace = $connection->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }
}
