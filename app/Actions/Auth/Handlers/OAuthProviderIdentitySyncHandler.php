<?php

declare(strict_types=1);

namespace App\Actions\Auth\Handlers;

use App\Enums\Auth\OAuthProvider;
use App\Models\ProviderIdentity;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Synchronizes the provider identity record and extracts Socialite token data.
 */
final readonly class OAuthProviderIdentitySyncHandler
{
    /**
     * Update or create the provider identity record.
     */
    public function sync(User $user, OAuthProvider $provider, SocialiteUser $socialiteUser): void
    {
        ProviderIdentity::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            [
                'provider_user_id' => $socialiteUser->getId(),
                'nickname' => $socialiteUser->getNickname(),
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
