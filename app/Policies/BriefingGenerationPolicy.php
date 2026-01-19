<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BriefingGeneration;
use App\Models\User;
use App\Models\Workspace;

final class BriefingGenerationPolicy
{
    /**
     * Determine whether the user can view any generations for a workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the generation.
     */
    public function view(User $user, BriefingGeneration $generation): bool
    {
        $workspace = $generation->workspace;

        if ($workspace === null) {
            return false;
        }

        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can create a generation.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }
}
