<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\HandlePolarWebhook;
use App\Actions\Webhooks\RecordIncomingWebhook;
use App\Enums\Billing\Partner;
use App\Models\IncomingWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PolarWebhookController
{
    /**
     * Handle incoming Polar webhook requests.
     *
     * Polar uses StandardWebhooks format requiring three headers.
     */
    public function handle(
        Request $request,
        RecordIncomingWebhook $recordIncomingWebhook,
        HandlePolarWebhook $handler,
    ): JsonResponse {
        $headers = [
            'webhook-id' => (string) $request->header('webhook-id', ''),
            'webhook-signature' => (string) $request->header('webhook-signature', ''),
            'webhook-timestamp' => (string) $request->header('webhook-timestamp', ''),
        ];
        $payload = $request->getContent();
        $webhookId = $headers['webhook-id'];

        // Parse payload to get event type
        $payloadArray = json_decode($payload, true);
        $eventType = is_array($payloadArray) ? ($payloadArray['type'] ?? null) : null;

        // Check for duplicate webhook
        if ($webhookId !== '' && IncomingWebhook::wasReceived(Partner::Polar, $webhookId)) {
            Log::debug('Skipping duplicate Polar webhook', ['webhook_id' => $webhookId]);

            return response()->json(['received' => true]);
        }

        // Record the incoming webhook
        /** @var array<string, mixed> $webhookPayload */
        $webhookPayload = is_array($payloadArray) ? $payloadArray : ['raw' => $payload];
        $incomingWebhook = $recordIncomingWebhook->handle(
            partner: Partner::Polar,
            payload: $webhookPayload,
            headers: $headers,
            webhookId: $webhookId !== '' ? $webhookId : null,
            eventType: is_string($eventType) ? $eventType : null,
            ipAddress: $request->ip(),
        );

        try {
            $handler->handle($payload, $headers);

            $incomingWebhook->markAsProcessed(Response::HTTP_OK, ['received' => true]);

            return response()->json(['received' => true]);
        } catch (Throwable $throwable) {
            Log::warning('Polar webhook failed', [
                'message' => $throwable->getMessage(),
                'webhook_id' => $webhookId,
            ]);

            $incomingWebhook->markAsProcessed(Response::HTTP_BAD_REQUEST, [
                'error' => 'Invalid webhook payload.',
                'message' => $throwable->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid webhook payload.'], Response::HTTP_BAD_REQUEST);
        }
    }
}
