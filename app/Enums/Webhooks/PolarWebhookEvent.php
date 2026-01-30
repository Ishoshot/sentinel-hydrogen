<?php

declare(strict_types=1);

namespace App\Enums\Webhooks;

/**
 * Polar webhook event types.
 *
 * @see https://polar.sh/docs/integrate/webhooks/events
 */
enum PolarWebhookEvent: string
{
    case Unknown = 'unknown';

    // Checkout events
    case CheckoutCreated = 'checkout.created';
    case CheckoutUpdated = 'checkout.updated';

    // Customer events
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeleted = 'customer.deleted';
    case CustomerStateChanged = 'customer.state_changed';

    // Customer seat events (for seat-based pricing)
    case CustomerSeatAssigned = 'customer_seat.assigned';
    case CustomerSeatClaimed = 'customer_seat.claimed';
    case CustomerSeatRevoked = 'customer_seat.revoked';

    // Subscription lifecycle events
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionActive = 'subscription.active';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionCanceled = 'subscription.canceled';
    case SubscriptionUncanceled = 'subscription.uncanceled';
    case SubscriptionRevoked = 'subscription.revoked';

    // Order events
    case OrderCreated = 'order.created';
    case OrderPaid = 'order.paid';
    case OrderUpdated = 'order.updated';
    case OrderRefunded = 'order.refunded';

    // Refund events
    case RefundCreated = 'refund.created';
    case RefundUpdated = 'refund.updated';

    // Benefit events
    case BenefitCreated = 'benefit.created';
    case BenefitUpdated = 'benefit.updated';

    // Benefit grant events
    case BenefitGrantCreated = 'benefit_grant.created';
    case BenefitGrantUpdated = 'benefit_grant.updated';
    case BenefitGrantRevoked = 'benefit_grant.revoked';
    case BenefitGrantCycled = 'benefit_grant.cycled';

    // Product events
    case ProductCreated = 'product.created';
    case ProductUpdated = 'product.updated';

    // Organization events
    case OrganizationUpdated = 'organization.updated';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for this event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::CheckoutCreated => 'Checkout Created',
            self::CheckoutUpdated => 'Checkout Updated',
            self::CustomerCreated => 'Customer Created',
            self::CustomerUpdated => 'Customer Updated',
            self::CustomerDeleted => 'Customer Deleted',
            self::CustomerStateChanged => 'Customer State Changed',
            self::CustomerSeatAssigned => 'Customer Seat Assigned',
            self::CustomerSeatClaimed => 'Customer Seat Claimed',
            self::CustomerSeatRevoked => 'Customer Seat Revoked',
            self::SubscriptionCreated => 'Subscription Created',
            self::SubscriptionActive => 'Subscription Active',
            self::SubscriptionUpdated => 'Subscription Updated',
            self::SubscriptionCanceled => 'Subscription Canceled',
            self::SubscriptionUncanceled => 'Subscription Uncanceled',
            self::SubscriptionRevoked => 'Subscription Revoked',
            self::OrderCreated => 'Order Created',
            self::OrderPaid => 'Order Paid',
            self::OrderUpdated => 'Order Updated',
            self::OrderRefunded => 'Order Refunded',
            self::RefundCreated => 'Refund Created',
            self::RefundUpdated => 'Refund Updated',
            self::BenefitCreated => 'Benefit Created',
            self::BenefitUpdated => 'Benefit Updated',
            self::BenefitGrantCreated => 'Benefit Grant Created',
            self::BenefitGrantUpdated => 'Benefit Grant Updated',
            self::BenefitGrantRevoked => 'Benefit Grant Revoked',
            self::BenefitGrantCycled => 'Benefit Grant Cycled',
            self::ProductCreated => 'Product Created',
            self::ProductUpdated => 'Product Updated',
            self::OrganizationUpdated => 'Organization Updated',
        };
    }

    /**
     * Check if this event is related to subscription lifecycle.
     */
    public function isSubscriptionEvent(): bool
    {
        return in_array($this, [
            self::SubscriptionCreated,
            self::SubscriptionActive,
            self::SubscriptionUpdated,
            self::SubscriptionCanceled,
            self::SubscriptionUncanceled,
            self::SubscriptionRevoked,
        ], true);
    }

    /**
     * Check if this event is related to checkout.
     */
    public function isCheckoutEvent(): bool
    {
        return in_array($this, [
            self::CheckoutCreated,
            self::CheckoutUpdated,
        ], true);
    }

    /**
     * Check if this event is related to orders/payments.
     */
    public function isOrderEvent(): bool
    {
        return in_array($this, [
            self::OrderCreated,
            self::OrderPaid,
            self::OrderUpdated,
            self::OrderRefunded,
        ], true);
    }

    /**
     * Check if this event indicates a payment failure.
     */
    public function isPaymentFailure(): bool
    {
        return false; // Polar doesn't have a specific payment.failed event
    }
}
