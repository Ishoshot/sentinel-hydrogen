<?php

declare(strict_types=1);

namespace App\Exceptions\Rendering;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Renders InvalidArgumentException as 422 JSON response for API requests.
 */
final class InvalidArgumentJsonRenderer implements ExceptionRenderer
{
    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function render(Throwable $e, Request $request): mixed
    {
        if (! $e instanceof InvalidArgumentException) {
            return null;
        }

        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json(['message' => $e->getMessage()], 422);
    }
}
