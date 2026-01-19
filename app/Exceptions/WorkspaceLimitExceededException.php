<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exception thrown when workspace creation is not allowed due to plan limits.
 */
final class WorkspaceLimitExceededException extends Exception
{
    /**
     * Create a new workspace limit exceeded exception.
     */
    public function __construct(
        string $message = 'Paid plan required to create additional workspaces',
        private readonly ?string $errorCode = 'paid_plan_required',
    ) {
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->errorCode ?? 'paid_plan_required',
        ], 403);
    }
}
