<?php

declare(strict_types=1);

use App\Actions\Billing\Resolvers\PolarSubscriptionSyncPayloadResolver;
use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncPayload;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;

it('resolves a complete sync payload with all fields', function (): void {
    Plan::factory()->illuminate()->create();

    $subscription = [
        'status' => 'active',
        'product' => [
            'metadata' => ['plan_tier' => PlanTier::Illuminate->value],
        ],
        'current_period_start' => '2026-02-01T00:00:00Z',
        'current_period_end' => '2026-03-01T00:00:00Z',
        'recurring_interval' => 'monthly',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result)->toBeInstanceOf(PolarSubscriptionSyncPayload::class)
        ->and($result->status)->toBe(SubscriptionStatus::Active)
        ->and($result->plan)->not->toBeNull()
        ->and($result->plan->tier)->toBe(PlanTier::Illuminate->value)
        ->and($result->billingInterval)->toBe(BillingInterval::Monthly)
        ->and($result->periodStart)->not->toBeNull()
        ->and($result->periodStart->toDateString())->toBe('2026-02-01')
        ->and($result->periodEnd)->not->toBeNull()
        ->and($result->periodEnd->toDateString())->toBe('2026-03-01');
});

it('uses override status when provided instead of polar status', function (): void {
    $subscription = [
        'status' => 'past_due',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, SubscriptionStatus::Active);

    expect($result->status)->toBe(SubscriptionStatus::Active);
});

it('maps polar statuses correctly when no override is provided', function (string $polarStatus, SubscriptionStatus $expectedStatus): void {
    $subscription = [
        'status' => $polarStatus,
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->status)->toBe($expectedStatus);
})->with([
    'active' => ['active', SubscriptionStatus::Active],
    'trialing' => ['trialing', SubscriptionStatus::Trialing],
    'past_due' => ['past_due', SubscriptionStatus::PastDue],
    'unpaid' => ['unpaid', SubscriptionStatus::PastDue],
    'canceled' => ['canceled', SubscriptionStatus::Canceled],
    'revoked' => ['revoked', SubscriptionStatus::Revoked],
    'unknown status defaults to active' => ['some_new_status', SubscriptionStatus::Active],
]);

it('returns null plan when product metadata is missing', function (): void {
    $subscription = [
        'status' => 'active',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->plan)->toBeNull();
});

it('returns null plan when product is not an array', function (): void {
    $subscription = [
        'status' => 'active',
        'product' => 'not-an-array',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->plan)->toBeNull();
});

it('returns null plan when plan_tier is not a string', function (): void {
    $subscription = [
        'status' => 'active',
        'product' => [
            'metadata' => ['plan_tier' => 123],
        ],
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->plan)->toBeNull();
});

it('returns null plan when plan_tier is not a valid tier', function (): void {
    $subscription = [
        'status' => 'active',
        'product' => [
            'metadata' => ['plan_tier' => 'nonexistent_tier'],
        ],
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->plan)->toBeNull();
});

it('returns null period dates when timestamps are missing', function (): void {
    $subscription = [
        'status' => 'active',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->periodStart)->toBeNull()
        ->and($result->periodEnd)->toBeNull();
});

it('returns null billing interval when recurring_interval is missing', function (): void {
    $subscription = [
        'status' => 'active',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->billingInterval)->toBeNull();
});

it('resolves yearly billing interval', function (): void {
    $subscription = [
        'status' => 'active',
        'recurring_interval' => 'yearly',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->billingInterval)->toBe(BillingInterval::Yearly);
});

it('builds update attributes with all fields when present', function (): void {
    Plan::factory()->illuminate()->create();

    $subscription = [
        'status' => 'active',
        'product' => [
            'metadata' => ['plan_tier' => PlanTier::Illuminate->value],
        ],
        'current_period_start' => '2026-02-01T00:00:00Z',
        'current_period_end' => '2026-03-01T00:00:00Z',
        'recurring_interval' => 'monthly',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->updateAttributes)->toHaveKey('status', SubscriptionStatus::Active)
        ->and($result->updateAttributes)->toHaveKey('plan_id')
        ->and($result->updateAttributes)->toHaveKey('billing_interval', BillingInterval::Monthly)
        ->and($result->updateAttributes)->toHaveKey('current_period_start')
        ->and($result->updateAttributes)->toHaveKey('current_period_end');
});

it('builds update attributes with only status when other fields are missing', function (): void {
    $subscription = [
        'status' => 'active',
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->updateAttributes)->toHaveKey('status', SubscriptionStatus::Active)
        ->and($result->updateAttributes)->not->toHaveKey('plan_id')
        ->and($result->updateAttributes)->not->toHaveKey('billing_interval')
        ->and($result->updateAttributes)->not->toHaveKey('current_period_start')
        ->and($result->updateAttributes)->not->toHaveKey('current_period_end');
});

it('returns null plan when product metadata has no plan_tier key', function (): void {
    $subscription = [
        'status' => 'active',
        'product' => [
            'metadata' => ['some_other_key' => 'value'],
        ],
    ];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->plan)->toBeNull();
});

it('defaults to active status when polar status is null', function (): void {
    $subscription = [];

    $result = app(PolarSubscriptionSyncPayloadResolver::class)->resolve($subscription, null);

    expect($result->status)->toBe(SubscriptionStatus::Active);
});
