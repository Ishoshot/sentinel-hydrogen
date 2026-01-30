<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Mark the getting started guide as seen for a user.
 */
final class MarkGettingStartedAsSeen
{
    /**
     * Mark the getting started guide as seen.
     */
    public function handle(User $user): User
    {
        $user->has_seen_getting_started = true;
        $user->save();

        return $user;
    }
}
