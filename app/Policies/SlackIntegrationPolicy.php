<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SlackIntegration;
use App\Models\User;
use App\Models\Workspace;

final class SlackIntegrationPolicy
{
    /**
     * Determine whether the user can view Slack integration status.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can connect Slack.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }

    /**
     * Determine whether the user can update the Slack integration.
     */
    public function update(User $user, SlackIntegration $integration): bool
    {
        return $this->canManageIntegration($user, $integration);
    }

    /**
     * Determine whether the user can disconnect Slack.
     */
    public function delete(User $user, SlackIntegration $integration): bool
    {
        return $this->canManageIntegration($user, $integration);
    }

    /**
     * Determine whether the user can manage the given integration.
     */
    private function canManageIntegration(User $user, SlackIntegration $integration): bool
    {
        $workspace = $integration->workspace;

        if ($workspace === null) {
            return false;
        }

        $role = $user->roleInWorkspace($workspace);

        return $role?->canManageSettings() ?? false;
    }
}
