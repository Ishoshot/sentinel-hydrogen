<?php

declare(strict_types=1);

namespace App\Exceptions\Rendering;

use Illuminate\Http\Request;
use Throwable;

/**
 * Redirects OAuth callback errors to the frontend error page.
 */
final class OAuthCallbackRedirectRenderer implements ExceptionRenderer
{
    /**
     * @return \Illuminate\Http\RedirectResponse|null
     */
    public function render(Throwable $e, Request $request): mixed
    {
        if (! $request->is('auth/*/callback')) {
            return null;
        }

        $frontendUrl = (string) config('app.frontend_url');
        $provider = $request->route('provider');

        $message = is_string($provider)
            ? 'Failed to authenticate with '.ucfirst($provider).'. Please try again.'
            : 'Authentication failed. Please try again.';

        return redirect($frontendUrl.'/auth/error?message='.urlencode($message));
    }
}
