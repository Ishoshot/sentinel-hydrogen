<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\HandleOAuthCallback;
use App\Actions\Auth\LogoutUser;
use App\Enums\Auth\OAuthProvider;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class OAuthController
{
    /**
     * Redirect the user to the OAuth provider.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $oauthProvider = OAuthProvider::tryFrom($provider);

        if ($oauthProvider === null) {
            abort(404, 'Unknown OAuth provider.');
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the OAuth provider callback.
     */
    public function callback(
        string $provider,
        HandleOAuthCallback $handleOAuthCallback,
    ): RedirectResponse {
        $oauthProvider = OAuthProvider::tryFrom($provider);

        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        if ($oauthProvider === null) {
            return redirect($frontendUrl.'/auth/error?message='.urlencode('Unknown OAuth provider.'));
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        /** @var SocialiteUser $socialiteUser */
        $socialiteUser = $driver->stateless()->user();

        if ($socialiteUser->getEmail() === null) {
            return redirect($frontendUrl.'/auth/error?message='.urlencode('Your '.$oauthProvider->label().' account does not have a verified email address.'));
        }

        $user = $handleOAuthCallback->handle($oauthProvider, $socialiteUser);

        $token = $user->createToken('auth-token')->plainTextToken;

        return redirect($frontendUrl.'/auth/callback?token='.$token);
    }

    /**
     * Log the user out by revoking the current access token.
     */
    public function logout(Request $request, LogoutUser $logoutUser): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $logoutUser->handle($user);
        }

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }
}
