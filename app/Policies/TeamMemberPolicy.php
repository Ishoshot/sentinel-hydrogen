<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\TeamMember;
use App\Models\User;

final class TeamMemberPolicy
{
    /**
     * Determine whether the user can view any team members.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the team member.
     */
    public function view(User $user, TeamMember $member): bool
    {
        $workspace = $member->workspace;

        if ($workspace === null) {
            return false;
        }

        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can update the team member.
     */
    public function update(User $user, TeamMember $member): bool
    {
        if ($member->role === TeamRole::Owner) {
            return false;
        }

        $workspace = $member->workspace;

        if ($workspace === null) {
            return false;
        }

        $userRole = $user->roleInWorkspace($workspace);

        if ($member->role === TeamRole::Admin && $userRole !== TeamRole::Owner) {
            return false;
        }

        return $userRole?->canManageMembers() ?? false;
    }

    /**
     * Determine whether the user can delete the team member.
     */
    public function delete(User $user, TeamMember $member): bool
    {
        if ($member->role === TeamRole::Owner) {
            return false;
        }

        if ($member->user_id === $user->id) {
            return true;
        }

        $workspace = $member->workspace;

        if ($workspace === null) {
            return false;
        }

        $userRole = $user->roleInWorkspace($workspace);

        return $userRole?->canManageMembers() ?? false;
    }
}
