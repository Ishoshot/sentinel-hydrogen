<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\HandlePolarWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PolarWebhookController
{
    /**
     * Handle incoming Polar webhook requests.
     *
     * Polar uses StandardWebhooks format requiring three headers.
     */
    public function handle(Request $request, HandlePolarWebhook $handler): JsonResponse
    {
        Log::info('Polar webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->getContent(),
        ]);

        $headers = [
            'webhook-id' => (string) $request->header('webhook-id', ''),
            'webhook-signature' => (string) $request->header('webhook-signature', ''),
            'webhook-timestamp' => (string) $request->header('webhook-timestamp', ''),
        ];
        $payload = $request->getContent();

        try {
            $handler->handle($payload, $headers);

            return response()->json(['received' => true]);
        } catch (Throwable $throwable) {
            Log::warning('Polar webhook failed', [
                'message' => $throwable->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid webhook payload.'], 400);
        }
    }
}
