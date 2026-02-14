<?php

declare(strict_types=1);

namespace App\Actions\Auth\Support;

use App\Actions\Workspaces\CreateWorkspaceForNewUser;
use App\Enums\Auth\OAuthProvider;
use App\Models\ProviderIdentity;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Resolves or creates a user from an OAuth callback with race-condition-safe locking.
 */
final readonly class OAuthUserResolver
{
    public function __construct(
        private CreateWorkspaceForNewUser $createWorkspaceForNewUser,
    ) {}

    /**
     * Find an existing user or create a new one.
     *
     * Uses lockForUpdate() to prevent race conditions where concurrent OAuth
     * callbacks with the same email could both pass existence checks and
     * attempt to create duplicate users.
     */
    public function resolve(OAuthProvider $provider, SocialiteUser $socialiteUser): User
    {
        $email = $socialiteUser->getEmail();

        if ($email === null) {
            throw new InvalidArgumentException('OAuth provider did not return an email address.');
        }

        // Check for existing provider identity with lock to prevent race conditions
        $existingIdentity = ProviderIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $socialiteUser->getId())
            ->lockForUpdate()
            ->first();

        if ($existingIdentity !== null && $existingIdentity->user !== null) {
            return $existingIdentity->user;
        }

        // Check for existing user by email with lock
        $existingUser = User::query()
            ->where('email', $email)
            ->lockForUpdate()
            ->first();

        if ($existingUser !== null) {
            return $existingUser;
        }

        return $this->createNewUser($socialiteUser);
    }

    /**
     * Create a new user from the Socialite data.
     */
    private function createNewUser(SocialiteUser $socialiteUser): User
    {
        $user = User::create([
            'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User',
            'email' => $socialiteUser->getEmail(),
            'avatar_url' => $socialiteUser->getAvatar(),
            'email_verified_at' => now(),
            'password' => null,
        ]);

        $this->createWorkspaceForNewUser->handle($user);

        $user->notify(new WelcomeNotification);

        return $user;
    }
}
