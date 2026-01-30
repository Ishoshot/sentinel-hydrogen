<?php

declare(strict_types=1);

namespace App\Exceptions\Rendering;

use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Renders billing/subscription RuntimeExceptions as 400 Bad Request.
 */
final class BillingRuntimeExceptionRenderer implements ExceptionRenderer
{
    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function render(Throwable $e, Request $request): mixed
    {
        if (! $e instanceof RuntimeException) {
            return null;
        }

        if (! $request->is('api/*/subscriptions*') && ! $request->is('api/*/billing*')) {
            return null;
        }

        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json(['message' => $e->getMessage()], 400);
    }
}
