<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Log out a user by revoking their current access token.
 */
final class LogoutUser
{
    /**
     * Revoke the user's current access token.
     */
    public function handle(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
