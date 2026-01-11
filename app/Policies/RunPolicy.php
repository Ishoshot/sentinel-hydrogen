<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;

final class RunPolicy
{
    /**
     * Determine whether the user can view any runs for a workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the run.
     */
    public function view(User $user, Run $run): bool
    {
        $workspace = $run->workspace;

        if ($workspace === null) {
            return false;
        }

        return $user->belongsToWorkspace($workspace);
    }
}
