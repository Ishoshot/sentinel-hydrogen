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
     */
    public function handle(Request $request, HandlePolarWebhook $handler): JsonResponse
    {
        $signature = (string) $request->header('webhook-signature', '');
        $payload = $request->getContent();

        try {
            $handler->handle($payload, $signature);

            return response()->json(['received' => true]);
        } catch (Throwable $throwable) {
            Log::warning('Polar webhook failed', [
                'message' => $throwable->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid webhook payload.'], 400);
        }
    }
}
