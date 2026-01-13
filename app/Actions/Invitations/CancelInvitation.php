<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class CancelInvitation
{
    /**
     * Cancel a pending invitation.
     *
     * @throws InvalidArgumentException
     */
    public function handle(Invitation $invitation): void
    {
        $ctx = ['invitation_id' => $invitation->id, 'workspace_id' => $invitation->workspace_id];

        if ($invitation->isAccepted()) {
            Log::info('Cancel rejected - invitation already accepted', $ctx);

            throw new InvalidArgumentException('Cannot cancel an accepted invitation.');
        }

        Log::info('Invitation cancelled', $ctx);

        $invitation->delete();
    }
}
