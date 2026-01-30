<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Workspace;

/**
 * Get the current subscription for a workspace.
 */
final class GetWorkspaceSubscription
{
    /**
     * Load and return the workspace with its plan.
     */
    public function handle(Workspace $workspace): Workspace
    {
        $workspace->loadMissing('plan');

        return $workspace;
    }
}
