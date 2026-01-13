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

final readonly class RemoveTeamMember
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Remove a team member from their workspace.
     *
     * @throws InvalidArgumentException
     */
    public function handle(TeamMember $member, User $actor): void
    {
        $ctx = ['member_id' => $member->id, 'workspace_id' => $member->workspace_id, 'actor_id' => $actor->id];

        if ($member->role === TeamRole::Owner) {
            Log::warning('Attempted to remove workspace owner', $ctx);

            throw new InvalidArgumentException('Cannot remove the workspace owner.');
        }

        $workspace = $member->workspace;
        $removedUser = $member->user;
        $removedUserName = $removedUser->name ?? 'Unknown';

        $member->delete();

        if ($workspace !== null) {
            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::MemberRemoved,
                description: $removedUserName.' was removed from the workspace',
                actor: $actor,
                metadata: ['removed_user_id' => $removedUser?->id, 'removed_user_name' => $removedUserName],
            );
        }
    }
}
