<?php

declare(strict_types=1);

namespace App\Exceptions\Slack;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvalidSlackOAuthStateException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'Invalid or expired Slack OAuth state.',
        private readonly ?string $redirectUrl = null
    ) {
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'invalid_state',
            ], 400);
        }

        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        $redirectTo = $this->redirectUrl ?? $frontendUrl.'/auth/error?message='.urlencode($this->getMessage());

        return redirect()->to($redirectTo)
            ->with('error', $this->getMessage());
    }
}
