<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\User;
use App\Models\Workspace;

/**
 * Policy for briefing share authorization.
 */
final class BriefingSharePolicy
{
    /**
     * Determine whether the user can create a share for a generation.
     */
    public function create(User $user, Workspace $workspace, BriefingGeneration $generation): bool
    {
        if ($generation->workspace_id !== $workspace->id) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }

    /**
     * Determine whether the user can delete the share.
     */
    public function delete(User $user, BriefingShare $share): bool
    {
        $generation = $share->generation;

        if ($generation === null) {
            return false;
        }

        $workspace = $generation->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }
}
