<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\HandleOAuthCallback;
use App\Enums\OAuthProvider;
use App\Http\Resources\UserResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
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

        try {
            $socialiteUser = Socialite::driver($provider)->user();
        } catch (Exception) {
            return redirect($frontendUrl.'/auth/error?message='.urlencode('Failed to authenticate with '.$oauthProvider->label().'. Please try again.'));
        }

        if ($socialiteUser->getEmail() === null) {
            return redirect($frontendUrl.'/auth/error?message='.urlencode('Your '.$oauthProvider->label().' account does not have a verified email address.'));
        }

        $user = $handleOAuthCallback->execute($oauthProvider, $socialiteUser);

        $token = $user->createToken('auth-token')->plainTextToken;

        return redirect($frontendUrl.'/auth/callback?token='.$token);
    }

    /**
     * Log the user out by revoking the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Successfully logged out.',
            ]);
        }

        $user->currentAccessToken()->delete();

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
