<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\TeamMember;
use InvalidArgumentException;

final class RemoveTeamMember
{
    /**
     * Remove a team member from their workspace.
     *
     * @throws InvalidArgumentException
     */
    public function execute(TeamMember $member): void
    {
        if ($member->role === TeamRole::Owner) {
            throw new InvalidArgumentException('Cannot remove the workspace owner.');
        }

        $member->delete();
    }
}
