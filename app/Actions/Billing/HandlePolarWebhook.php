<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Webhooks\PolarWebhookEvent;
use App\Services\Billing\Contracts\PolarBillingServiceContract;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarBillingServiceContract $billingService,
        private HandlePolarOrderPaid $handlePolarOrderPaid,
        private HandlePolarOrderRefunded $handlePolarOrderRefunded,
        private HandlePolarSubscriptionEvent $handlePolarSubscriptionEvent,
    ) {}

    /**
     * Handle incoming Polar webhook events.
     *
     * @param  array{webhook-id: string, webhook-signature: string, webhook-timestamp: string}  $headers
     */
    public function handle(string $payload, array $headers): void
    {
        $webhook = $this->billingService->verifyWebhook($payload, $headers);

        if ($webhook->type === PolarWebhookEvent::Unknown) {
            Log::debug('Unhandled Polar webhook event type', ['type' => $webhook->type->value]);

            return;
        }

        match ($webhook->type) {
            PolarWebhookEvent::OrderPaid => $this->handlePolarOrderPaid->handle($webhook),
            PolarWebhookEvent::OrderRefunded => $this->handlePolarOrderRefunded->handle($webhook),
            PolarWebhookEvent::SubscriptionActive => $this->handlePolarSubscriptionEvent->active($webhook),
            PolarWebhookEvent::SubscriptionCanceled => $this->handlePolarSubscriptionEvent->canceled($webhook),
            PolarWebhookEvent::SubscriptionUncanceled => $this->handlePolarSubscriptionEvent->uncanceled($webhook),
            PolarWebhookEvent::SubscriptionRevoked => $this->handlePolarSubscriptionEvent->revoked($webhook),
            PolarWebhookEvent::SubscriptionUpdated => $this->handlePolarSubscriptionEvent->updated($webhook),
            PolarWebhookEvent::SubscriptionCreated => $this->handlePolarSubscriptionEvent->created($webhook),
            default => null,
        };
    }
}
