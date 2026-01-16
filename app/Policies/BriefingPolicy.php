<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Briefing;
use App\Models\User;
use App\Models\Workspace;

final class BriefingPolicy
{
    /**
     * Determine whether the user can view any briefings for a workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the briefing.
     */
    public function view(User $user, Briefing $briefing, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }
}
