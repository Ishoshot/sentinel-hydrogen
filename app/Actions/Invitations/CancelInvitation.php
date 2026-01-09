<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use InvalidArgumentException;

final class CancelInvitation
{
    /**
     * Cancel a pending invitation.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException('Cannot cancel an accepted invitation.');
        }

        $invitation->delete();
    }
}
