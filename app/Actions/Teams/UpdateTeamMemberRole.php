<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\TeamRole;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final readonly class UpdateTeamMemberRole
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Update a team member's role.
     *
     * @throws InvalidArgumentException
     */
    public function handle(TeamMember $member, TeamRole $role, User $actor): TeamMember
    {
        $ctx = ['member_id' => $member->id, 'workspace_id' => $member->workspace_id, 'actor_id' => $actor->id, 'new_role' => $role->value];

        if ($member->role === TeamRole::Owner) {
            Log::warning('Attempted to change owner role', $ctx);

            throw new InvalidArgumentException('Cannot change the role of the workspace owner.');
        }

        $oldRole = $member->role;
        $memberUser = $member->user;
        $memberName = $memberUser->name ?? 'Unknown';

        $member->update(['role' => $role]);

        $workspace = $member->workspace;

        if ($workspace !== null) {
            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::MemberRoleUpdated,
                description: sprintf("%s's role was changed from %s to %s", $memberName, $oldRole->label(), $role->label()),
                actor: $actor,
                subject: $member,
                metadata: ['old_role' => $oldRole->value, 'new_role' => $role->value],
            );
        }

        return $member->refresh();
    }
}
