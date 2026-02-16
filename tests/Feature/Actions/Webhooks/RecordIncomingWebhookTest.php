<?php

declare(strict_types=1);

use App\Actions\Webhooks\RecordIncomingWebhook;
use App\Enums\Billing\Partner;
use App\Models\IncomingWebhook;

it('records an incoming webhook', function (): void {
    $action = new RecordIncomingWebhook;

    $result = $action->handle(
        partner: Partner::Polar,
        payload: ['event' => 'test'],
        headers: ['X-Webhook-Id' => 'abc123'],
        webhookId: 'wh_123',
        eventType: 'order.paid',
        ipAddress: '127.0.0.1',
    );

    expect($result)->toBeInstanceOf(IncomingWebhook::class);
    expect($result->partner)->toBe(Partner::Polar);
    expect($result->webhook_id)->toBe('wh_123');
    expect($result->event_type)->toBe('order.paid');
    expect($result->ip_address)->toBe('127.0.0.1');
});

it('records webhook with minimal data', function (): void {
    $action = new RecordIncomingWebhook;

    $result = $action->handle(
        partner: Partner::GitHub,
        payload: ['action' => 'created'],
    );

    expect($result)->toBeInstanceOf(IncomingWebhook::class);
    expect($result->partner)->toBe(Partner::GitHub);
    expect($result->webhook_id)->toBeNull();
});
