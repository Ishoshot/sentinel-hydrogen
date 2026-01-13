<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationSentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

final readonly class ResendInvitation
{
    /**
     * Resend an invitation notification.
     *
     * @throws InvalidArgumentException
     */
    public function handle(Invitation $invitation): void
    {
        $ctx = ['invitation_id' => $invitation->id, 'workspace_id' => $invitation->workspace_id, 'email' => $invitation->email];

        if ($invitation->isAccepted()) {
            Log::info('Resend rejected - invitation already accepted', $ctx);

            throw new InvalidArgumentException('Cannot resend an accepted invitation.');
        }

        if ($invitation->isExpired()) {
            Log::info('Resend rejected - invitation expired', $ctx);

            throw new InvalidArgumentException('Cannot resend an expired invitation.');
        }

        // Check if invitee has an existing account
        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser !== null) {
            // User exists - send both email and DB notification
            $existingUser->notify(new InvitationSentNotification($invitation));
        } else {
            // No account yet - send email only (on-demand notification)
            Notification::route('mail', $invitation->email)
                ->notify(new InvitationSentNotification($invitation));
        }
    }
}
