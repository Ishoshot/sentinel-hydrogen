<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\TeamMember;
use InvalidArgumentException;

final class UpdateTeamMemberRole
{
    /**
     * Update a team member's role.
     *
     * @throws InvalidArgumentException
     */
    public function execute(TeamMember $member, TeamRole $role): TeamMember
    {
        if ($member->role === TeamRole::Owner) {
            throw new InvalidArgumentException('Cannot change the role of the workspace owner.');
        }

        $member->update(['role' => $role]);

        return $member->refresh();
    }
}
