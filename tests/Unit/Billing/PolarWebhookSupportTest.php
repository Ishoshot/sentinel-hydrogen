<?php

declare(strict_types=1);

use App\Actions\Billing\PolarWebhookSupport;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;

it('maps polar status trialing', function (): void {
    $support = new PolarWebhookSupport;

    expect($support->mapPolarStatus('trialing'))->toBe(SubscriptionStatus::Trialing);
});

it('maps polar status past_due', function (): void {
    $support = new PolarWebhookSupport;

    expect($support->mapPolarStatus('past_due'))->toBe(SubscriptionStatus::PastDue);
    expect($support->mapPolarStatus('unpaid'))->toBe(SubscriptionStatus::PastDue);
    expect($support->mapPolarStatus('incomplete'))->toBe(SubscriptionStatus::PastDue);
    expect($support->mapPolarStatus('incomplete_expired'))->toBe(SubscriptionStatus::PastDue);
});

it('maps polar status canceled', function (): void {
    $support = new PolarWebhookSupport;

    expect($support->mapPolarStatus('canceled'))->toBe(SubscriptionStatus::Canceled);
    expect($support->mapPolarStatus('cancelled'))->toBe(SubscriptionStatus::Canceled);
});

it('maps polar status revoked', function (): void {
    $support = new PolarWebhookSupport;

    expect($support->mapPolarStatus('revoked'))->toBe(SubscriptionStatus::Revoked);
});

it('maps unknown polar status to active', function (): void {
    $support = new PolarWebhookSupport;

    expect($support->mapPolarStatus('active'))->toBe(SubscriptionStatus::Active);
    expect($support->mapPolarStatus(null))->toBe(SubscriptionStatus::Active);
    expect($support->mapPolarStatus('unknown'))->toBe(SubscriptionStatus::Active);
});

it('extracts billing interval', function (): void {
    $support = new PolarWebhookSupport;

    $result = $support->extractBillingInterval(['recurring_interval' => 'monthly'], null);

    expect($result)->toBe(BillingInterval::Monthly);
});

it('parses timestamps', function (): void {
    $support = new PolarWebhookSupport;

    $result = $support->timestampToDateTime('2026-02-15T00:00:00Z');

    expect($result)->not->toBeNull();
    expect($result->year)->toBe(2026);
});

it('returns null for null timestamps', function (): void {
    $support = new PolarWebhookSupport;

    expect($support->timestampToDateTime(null))->toBeNull();
});
