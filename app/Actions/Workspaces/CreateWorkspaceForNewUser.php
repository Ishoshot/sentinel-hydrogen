<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;

final readonly class CreateWorkspaceForNewUser
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private CreateWorkspace $createWorkspace,
    ) {}

    /**
     * Create a personal workspace for a new user.
     */
    public function execute(User $user): Workspace
    {
        $workspaceName = $this->generateWorkspaceName($user);

        return $this->createWorkspace->execute(
            owner: $user,
            name: $workspaceName,
        );
    }

    /**
     * Generate a personalized workspace name for the user.
     */
    private function generateWorkspaceName(User $user): string
    {
        $name = $user->name;

        if (str_ends_with(mb_strtolower($name), 's')) {
            return $name."' Workspace";
        }

        return $name."'s Workspace";
    }
}
