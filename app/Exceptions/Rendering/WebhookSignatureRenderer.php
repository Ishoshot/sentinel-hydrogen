<?php

declare(strict_types=1);

namespace App\Exceptions\Rendering;

use Illuminate\Http\Request;
use RuntimeException;
use StandardWebhooks\Exception\WebhookVerificationException;
use Throwable;

/**
 * Renders webhook signature verification failures as 400 Bad Request.
 */
final class WebhookSignatureRenderer implements ExceptionRenderer
{
    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function render(Throwable $e, Request $request): mixed
    {
        if (! $request->is('webhooks/*')) {
            return null;
        }

        if ($e instanceof WebhookVerificationException) {
            return response()->json(['error' => 'Invalid webhook signature.'], 400);
        }

        if ($request->is('webhooks/polar') && $e instanceof RuntimeException && str_contains($e->getMessage(), 'webhook signature')) {
            return response()->json(['error' => 'Invalid webhook payload.'], 400);
        }

        return null;
    }
}
