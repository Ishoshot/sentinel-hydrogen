<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Enums\Workspace\TeamRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationSentNotification;
use App\Services\Logging\LogContext;
use App\Services\Plans\PlanLimitEnforcer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

final readonly class CreateInvitation
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
        private PlanLimitEnforcer $planLimitEnforcer,
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
        $ctx = LogContext::merge(LogContext::fromWorkspace($workspace), ['email' => $email, 'role' => $role->value]);

        if ($role === TeamRole::Owner) {
            Log::warning('Attempted to invite as owner', $ctx);

            throw new InvalidArgumentException('Cannot invite someone as owner.');
        }

        $limitCheck = $this->planLimitEnforcer->ensureCanInviteMember($workspace);

        if (! $limitCheck->allowed) {
            Log::info('Invitation blocked by team size limit', $ctx);

            throw new InvalidArgumentException($limitCheck->message ?? 'Team size limit reached.');
        }

        $existingMember = $workspace->teamMembers()
            ->whereHas('user', function (Builder $query) use ($email): void {
                $query->where('email', $email);
            })
            ->exists();

        if ($existingMember) {
            Log::info('Invitation rejected - user already member', $ctx);

            throw new InvalidArgumentException('This user is already a member of the workspace.');
        }

        $existingInvitation = $workspace->invitations()
            ->pending()
            ->where('email', $email)
            ->exists();

        if ($existingInvitation) {
            Log::info('Invitation rejected - pending invitation exists', $ctx);

            throw new InvalidArgumentException('An invitation has already been sent to this email.');
        }

        $team = $workspace->team;

        if ($team === null) {
            Log::error('Workspace missing team configuration', LogContext::fromWorkspace($workspace));

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
