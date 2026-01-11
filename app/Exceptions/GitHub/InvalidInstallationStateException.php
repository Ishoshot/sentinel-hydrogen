<?php

declare(strict_types=1);

namespace App\Exceptions\GitHub;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvalidInstallationStateException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'Invalid or expired installation state.',
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

        $redirectTo = $this->redirectUrl ?? '/workspaces';

        return redirect()->to($redirectTo)
            ->with('error', $this->getMessage());
    }
}
