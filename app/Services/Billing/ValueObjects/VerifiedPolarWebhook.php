<?php

declare(strict_types=1);

namespace App\Services\Billing\ValueObjects;

use App\Enums\Webhooks\PolarWebhookEvent;

/**
 * A verified and parsed Polar webhook payload.
 */
final readonly class VerifiedPolarWebhook
{
    /**
     * Create a new VerifiedPolarWebhook instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public PolarWebhookEvent $type,
        public array $data,
    ) {}

    /**
     * Create from the raw verified payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $type = PolarWebhookEvent::tryFrom($payload['type'] ?? '') ?? PolarWebhookEvent::Unknown;
        /** @var array<string, mixed> $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return new self(
            type: $type,
            data: $data,
        );
    }

    /**
     * Get a value from the data using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Get a string value from the data.
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : $default;
    }

    /**
     * Get an integer value from the data.
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get the workspace ID from metadata.
     */
    public function getWorkspaceId(): ?int
    {
        $id = $this->get('metadata.workspace_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Get the subscription ID.
     */
    public function getSubscriptionId(): ?string
    {
        $id = $this->get('subscription.id') ?? $this->get('id');

        return is_string($id) ? $id : null;
    }

    /**
     * Get the customer ID.
     */
    public function getCustomerId(): ?string
    {
        $id = $this->get('customer.id') ?? $this->get('subscription.customer_id');

        return is_string($id) ? $id : null;
    }

    /**
     * Check if this is an order-related event.
     */
    public function isOrderEvent(): bool
    {
        return $this->type === PolarWebhookEvent::OrderCreated;
    }

    /**
     * Check if this is a subscription-related event.
     */
    public function isSubscriptionEvent(): bool
    {
        return in_array($this->type, [
            PolarWebhookEvent::SubscriptionCreated,
            PolarWebhookEvent::SubscriptionUpdated,
            PolarWebhookEvent::SubscriptionActive,
            PolarWebhookEvent::SubscriptionCanceled,
            PolarWebhookEvent::SubscriptionRevoked,
        ], true);
    }

    /**
     * Convert to array for logging/debugging.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'data' => $this->data,
        ];
    }
}
