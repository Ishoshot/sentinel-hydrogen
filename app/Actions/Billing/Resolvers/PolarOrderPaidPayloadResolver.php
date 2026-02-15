<?php

declare(strict_types=1);

namespace App\Actions\Billing\Resolvers;

use App\Actions\Billing\PolarWebhookSupport;
use App\Actions\Billing\ValueObjects\PolarOrderPaidPayload;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

final readonly class PolarOrderPaidPayloadResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

    /**
     * Resolve structured payload data from a verified order.paid webhook.
     */
    public function resolve(VerifiedPolarWebhook $webhook): ?PolarOrderPaidPayload
    {
        $order = $webhook->data;

        if ($order === []) {
            return null;
        }

        $subscriptionData = is_array($order['subscription'] ?? null)
            ? $order['subscription']
            : null;
        /** @var array<string, mixed>|null $subscriptionData */
        $subscriptionId = is_array($subscriptionData)
            ? ($subscriptionData['id'] ?? null)
            : ($order['subscription_id'] ?? null);

        $customerData = $order['customer'] ?? null;
        $customerId = is_array($customerData)
            ? ($customerData['id'] ?? null)
            : ($order['customer_id'] ?? null);

        $metadata = $order['metadata'] ?? [];
        if (is_array($subscriptionData) && $metadata === []) {
            $metadata = $subscriptionData['metadata'] ?? [];
        }

        /** @var array<string, mixed> $metadata */

        /** @var array<string, mixed> $typedOrder */
        $typedOrder = $order;

        return new PolarOrderPaidPayload(
            order: $typedOrder,
            subscriptionData: $subscriptionData,
            subscriptionId: is_string($subscriptionId) ? $subscriptionId : null,
            customerId: is_string($customerId) ? $customerId : null,
            workspaceId: $metadata['workspace_id'] ?? null,
            planTier: $metadata['plan_tier'] ?? null,
            promotionId: $metadata['promotion_id'] ?? null,
            billingInterval: $this->polarWebhookSupport->extractBillingInterval($subscriptionData, $typedOrder),
        );
    }
}
