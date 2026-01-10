<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\TeamRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use InvalidArgumentException;

final readonly class CreateInvitation
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Create a new invitation to join a workspace.
     *
     * @throws InvalidArgumentException
     */
    public function handle(
        Workspace $workspace,
        User $invitedBy,
        string $email,
        TeamRole $role = TeamRole::Member,
    ): Invitation {
        if ($role === TeamRole::Owner) {
            throw new InvalidArgumentException('Cannot invite someone as owner.');
        }

        $existingMember = $workspace->teamMembers()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($existingMember) {
            throw new InvalidArgumentException('This user is already a member of the workspace.');
        }

        $existingInvitation = $workspace->invitations()
            ->pending()
            ->where('email', $email)
            ->exists();

        if ($existingInvitation) {
            throw new InvalidArgumentException('An invitation has already been sent to this email.');
        }

        $team = $workspace->team;

        if ($team === null) {
            throw new InvalidArgumentException('The workspace does not have a team configured.');
        }

        $invitation = Invitation::create([
            'email' => $email,
            'workspace_id' => $workspace->id,
            'team_id' => $team->id,
            'invited_by_id' => $invitedBy->id,
            'role' => $role,
        ]);

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::MemberInvited,
            description: sprintf('%s invited %s as %s', $invitedBy->name, $email, $role->label()),
            actor: $invitedBy,
            subject: $invitation,
            metadata: ['email' => $email, 'role' => $role->value],
        );

        return $invitation;
    }
}
