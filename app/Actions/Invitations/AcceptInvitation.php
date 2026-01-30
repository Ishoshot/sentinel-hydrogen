<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Invitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Plans\PlanLimitEnforcer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final readonly class AcceptInvitation
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
        private PlanLimitEnforcer $planLimitEnforcer,
    ) {}

    /**
     * Accept an invitation and create the team membership.
     *
     * @throws InvalidArgumentException
     */
    public function handle(Invitation $invitation, User $user): TeamMember
    {
        $ctx = ['invitation_id' => $invitation->id, 'user_id' => $user->id, 'workspace_id' => $invitation->workspace_id];

        if ($invitation->isExpired()) {
            Log::info('Invitation acceptance rejected - expired', $ctx);

            throw new InvalidArgumentException('This invitation has expired.');
        }

        if ($invitation->isAccepted()) {
            Log::info('Invitation acceptance rejected - already accepted', $ctx);

            throw new InvalidArgumentException('This invitation has already been accepted.');
        }

        $workspace = $invitation->workspace;

        if ($workspace === null) {
            Log::warning('Invitation acceptance failed - workspace deleted', $ctx);

            throw new InvalidArgumentException('The workspace for this invitation no longer exists.');
        }

        $existingMember = $workspace->teamMembers()
            ->where('user_id', $user->id)
            ->exists();

        if ($existingMember) {
            Log::info('Invitation acceptance rejected - already a member', $ctx);

            throw new InvalidArgumentException('You are already a member of this workspace.');
        }

        // Check team size limit before accepting
        $limitCheck = $this->planLimitEnforcer->ensureCanInviteMember($workspace);

        if (! $limitCheck->allowed) {
            Log::info('Invitation acceptance rejected - team size limit reached', $ctx);

            throw new InvalidArgumentException($limitCheck->message ?? 'This workspace has reached its team size limit.');
        }

        return DB::transaction(function () use ($invitation, $user, $workspace): TeamMember {
            $member = TeamMember::create([
                'user_id' => $user->id,
                'team_id' => $invitation->team_id,
                'workspace_id' => $invitation->workspace_id,
                'role' => $invitation->role,
                'joined_at' => now(),
            ]);

            $invitation->markAsAccepted();

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::MemberJoined,
                description: sprintf('%s joined the workspace as %s', $user->name, $invitation->role->label()),
                actor: $user,
                subject: $member,
                metadata: ['role' => $invitation->role->value],
            );

            return $member;
        });
    }
}
