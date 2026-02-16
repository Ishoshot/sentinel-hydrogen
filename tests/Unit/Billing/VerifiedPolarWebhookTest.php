<?php

declare(strict_types=1);

use App\Enums\Webhooks\PolarWebhookEvent;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

it('creates from array with known event type', function (): void {
    $webhook = VerifiedPolarWebhook::fromArray([
        'type' => 'order.paid',
        'data' => ['id' => 'order_123'],
    ]);

    expect($webhook->type)->toBe(PolarWebhookEvent::OrderPaid);
    expect($webhook->data)->toBe(['id' => 'order_123']);
});

it('creates from array with unknown event type', function (): void {
    $webhook = VerifiedPolarWebhook::fromArray([
        'type' => 'some.unknown.event',
        'data' => [],
    ]);

    expect($webhook->type)->toBe(PolarWebhookEvent::Unknown);
});

it('creates from array with missing type', function (): void {
    $webhook = VerifiedPolarWebhook::fromArray(['data' => ['key' => 'value']]);

    expect($webhook->type)->toBe(PolarWebhookEvent::Unknown);
    expect($webhook->data)->toBe(['key' => 'value']);
});

it('creates from array with non-array data', function (): void {
    $webhook = VerifiedPolarWebhook::fromArray([
        'type' => 'order.paid',
        'data' => 'not-an-array',
    ]);

    expect($webhook->data)->toBe([]);
});

it('gets value using dot notation', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['nested' => ['key' => 'value']],
    );

    expect($webhook->get('nested.key'))->toBe('value');
    expect($webhook->get('missing', 'default'))->toBe('default');
});

it('gets string value', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['name' => 'test', 'number' => 123],
    );

    expect($webhook->getString('name'))->toBe('test');
    expect($webhook->getString('number'))->toBe('');
    expect($webhook->getString('missing', 'fallback'))->toBe('fallback');
});

it('gets int value', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['count' => 42, 'text' => 'not-a-number'],
    );

    expect($webhook->getInt('count'))->toBe(42);
    expect($webhook->getInt('text'))->toBe(0);
    expect($webhook->getInt('missing', 99))->toBe(99);
});

it('gets workspace id from metadata', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['metadata' => ['workspace_id' => '123']],
    );

    expect($webhook->getWorkspaceId())->toBe(123);
});

it('returns null for non-numeric workspace id', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['metadata' => ['workspace_id' => 'abc']],
    );

    expect($webhook->getWorkspaceId())->toBeNull();
});

it('gets subscription id', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCreated,
        data: ['subscription' => ['id' => 'sub_123']],
    );

    expect($webhook->getSubscriptionId())->toBe('sub_123');
});

it('gets subscription id from top-level id', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCreated,
        data: ['id' => 'sub_456'],
    );

    expect($webhook->getSubscriptionId())->toBe('sub_456');
});

it('gets customer id', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['customer' => ['id' => 'cus_123']],
    );

    expect($webhook->getCustomerId())->toBe('cus_123');
});

it('identifies order event', function (): void {
    $orderWebhook = new VerifiedPolarWebhook(type: PolarWebhookEvent::OrderCreated, data: []);
    $subWebhook = new VerifiedPolarWebhook(type: PolarWebhookEvent::SubscriptionCreated, data: []);

    expect($orderWebhook->isOrderEvent())->toBeTrue();
    expect($subWebhook->isOrderEvent())->toBeFalse();
});

it('identifies subscription events', function (): void {
    $subWebhook = new VerifiedPolarWebhook(type: PolarWebhookEvent::SubscriptionCreated, data: []);
    $orderWebhook = new VerifiedPolarWebhook(type: PolarWebhookEvent::OrderPaid, data: []);

    expect($subWebhook->isSubscriptionEvent())->toBeTrue();
    expect($orderWebhook->isSubscriptionEvent())->toBeFalse();
});

it('converts to array', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: ['id' => 'test'],
    );

    expect($webhook->toArray())->toBe([
        'type' => 'order.paid',
        'data' => ['id' => 'test'],
    ]);
});
