<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\TeamRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationSentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
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
            ->whereHas('user', function (Builder $query) use ($email): void {
                $query->where('email', $email);
            })
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

        // Send notification to invitee
        $this->sendInvitationNotification($invitation, $email);

        return $invitation;
    }

    /**
     * Send the invitation notification to the invitee.
     */
    private function sendInvitationNotification(Invitation $invitation, string $email): void
    {
        // Check if invitee has an existing account
        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            // User exists - send both email and DB notification
            $existingUser->notify(new InvitationSentNotification($invitation));
        } else {
            // No account yet - send email only (on-demand notification)
            Notification::route('mail', $email)
                ->notify(new InvitationSentNotification($invitation));
        }
    }
}
