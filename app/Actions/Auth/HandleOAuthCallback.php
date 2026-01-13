<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Workspaces\CreateWorkspaceForNewUser;
use App\Enums\OAuthProvider;
use App\Models\ProviderIdentity;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class HandleOAuthCallback
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private CreateWorkspaceForNewUser $createWorkspaceForNewUser,
    ) {}

    /**
     * Handle the OAuth callback and authenticate the user.
     */
    public function handle(OAuthProvider $provider, SocialiteUser $socialiteUser): User
    {
        return DB::transaction(function () use ($provider, $socialiteUser): User {
            $user = $this->findOrCreateUser($provider, $socialiteUser);

            $this->updateProviderIdentity($user, $provider, $socialiteUser);

            return $user;
        });
    }

    /**
     * Find an existing user or create a new one.
     *
     * Uses lockForUpdate() to prevent race conditions where concurrent OAuth
     * callbacks with the same email could both pass existence checks and
     * attempt to create duplicate users.
     */
    private function findOrCreateUser(OAuthProvider $provider, SocialiteUser $socialiteUser): User
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

    /**
     * Update or create the provider identity record.
     */
    private function updateProviderIdentity(User $user, OAuthProvider $provider, SocialiteUser $socialiteUser): void
    {
        ProviderIdentity::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            [
                'provider_user_id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName(),
                'avatar_url' => $socialiteUser->getAvatar(),
                'access_token' => $this->getAccessToken($socialiteUser),
                'refresh_token' => $this->getRefreshToken($socialiteUser),
                'token_expires_at' => $this->getTokenExpiresAt($socialiteUser),
            ]
        );

        if ($user->avatar_url === null && $socialiteUser->getAvatar() !== null) {
            $user->update(['avatar_url' => $socialiteUser->getAvatar()]);
        }
    }

    /**
     * Get the access token from the Socialite user.
     */
    private function getAccessToken(SocialiteUser $socialiteUser): ?string
    {
        if (! property_exists($socialiteUser, 'token')) {
            return null;
        }

        /** @var mixed $token */
        $token = $socialiteUser->token;

        return is_string($token) ? $token : null;
    }

    /**
     * Get the refresh token from the Socialite user.
     */
    private function getRefreshToken(SocialiteUser $socialiteUser): ?string
    {
        if (! property_exists($socialiteUser, 'refreshToken')) {
            return null;
        }

        /** @var mixed $refreshToken */
        $refreshToken = $socialiteUser->refreshToken;

        return is_string($refreshToken) ? $refreshToken : null;
    }

    /**
     * Get the token expiration timestamp from the Socialite user.
     */
    private function getTokenExpiresAt(SocialiteUser $socialiteUser): ?\Illuminate\Support\Carbon
    {
        if (! property_exists($socialiteUser, 'expiresIn')) {
            return null;
        }

        /** @var mixed $expiresIn */
        $expiresIn = $socialiteUser->expiresIn;

        if (! is_int($expiresIn)) {
            return null;
        }

        return now()->addSeconds($expiresIn);
    }
}
