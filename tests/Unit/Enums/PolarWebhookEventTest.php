<?php

declare(strict_types=1);

use App\Enums\Webhooks\PolarWebhookEvent;

it('returns all values', function (): void {
    $values = PolarWebhookEvent::values();

    expect($values)->toBeArray()
        ->toContain('unknown')
        ->toContain('subscription.created')
        ->toContain('order.paid')
        ->toContain('order.refunded');
});

it('returns correct labels for key events', function (): void {
    expect(PolarWebhookEvent::Unknown->label())->toBe('Unknown');
    expect(PolarWebhookEvent::SubscriptionCreated->label())->toBe('Subscription Created');
    expect(PolarWebhookEvent::OrderPaid->label())->toBe('Order Paid');
    expect(PolarWebhookEvent::OrderRefunded->label())->toBe('Order Refunded');
    expect(PolarWebhookEvent::CheckoutCreated->label())->toBe('Checkout Created');
    expect(PolarWebhookEvent::CustomerDeleted->label())->toBe('Customer Deleted');
});

it('identifies subscription events', function (): void {
    expect(PolarWebhookEvent::SubscriptionCreated->isSubscriptionEvent())->toBeTrue();
    expect(PolarWebhookEvent::SubscriptionActive->isSubscriptionEvent())->toBeTrue();
    expect(PolarWebhookEvent::SubscriptionUpdated->isSubscriptionEvent())->toBeTrue();
    expect(PolarWebhookEvent::SubscriptionCanceled->isSubscriptionEvent())->toBeTrue();
    expect(PolarWebhookEvent::SubscriptionUncanceled->isSubscriptionEvent())->toBeTrue();
    expect(PolarWebhookEvent::SubscriptionRevoked->isSubscriptionEvent())->toBeTrue();
    expect(PolarWebhookEvent::OrderPaid->isSubscriptionEvent())->toBeFalse();
});

it('identifies checkout events', function (): void {
    expect(PolarWebhookEvent::CheckoutCreated->isCheckoutEvent())->toBeTrue();
    expect(PolarWebhookEvent::CheckoutUpdated->isCheckoutEvent())->toBeTrue();
    expect(PolarWebhookEvent::OrderPaid->isCheckoutEvent())->toBeFalse();
});

it('identifies order events', function (): void {
    expect(PolarWebhookEvent::OrderCreated->isOrderEvent())->toBeTrue();
    expect(PolarWebhookEvent::OrderPaid->isOrderEvent())->toBeTrue();
    expect(PolarWebhookEvent::OrderUpdated->isOrderEvent())->toBeTrue();
    expect(PolarWebhookEvent::OrderRefunded->isOrderEvent())->toBeTrue();
    expect(PolarWebhookEvent::SubscriptionCreated->isOrderEvent())->toBeFalse();
});

it('reports no payment failure events', function (): void {
    expect(PolarWebhookEvent::OrderRefunded->isPaymentFailure())->toBeFalse();
    expect(PolarWebhookEvent::Unknown->isPaymentFailure())->toBeFalse();
});
