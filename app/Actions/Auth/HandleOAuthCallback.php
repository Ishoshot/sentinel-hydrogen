<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Auth\Resolvers\OAuthUserResolver;
use App\Actions\Auth\Support\OAuthProviderIdentitySync;
use App\Enums\Auth\OAuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class HandleOAuthCallback
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private OAuthUserResolver $userResolver,
        private OAuthProviderIdentitySync $identitySync = new OAuthProviderIdentitySync,
    ) {}

    /**
     * Handle the OAuth callback and authenticate the user.
     */
    public function handle(OAuthProvider $provider, SocialiteUser $socialiteUser): User
    {
        return DB::transaction(function () use ($provider, $socialiteUser): User {
            $user = $this->userResolver->resolve($provider, $socialiteUser);

            $this->identitySync->sync($user, $provider, $socialiteUser);

            return $user;
        });
    }
}
