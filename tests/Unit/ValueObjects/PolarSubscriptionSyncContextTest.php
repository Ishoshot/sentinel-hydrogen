<?php

declare(strict_types=1);

use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncContext;
use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncPayload;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

it('stores subscription and sync payload correctly', function (): void {
    $subscription = (new Subscription)->forceFill(['id' => 1, 'polar_customer_id' => 'cust_abc']);
    $syncPayload = new PolarSubscriptionSyncPayload(
        status: SubscriptionStatus::Active,
        plan: null,
        billingInterval: BillingInterval::Monthly,
        periodStart: CarbonImmutable::parse('2026-01-01'),
        periodEnd: CarbonImmutable::parse('2026-02-01'),
        updateAttributes: ['status' => 'active'],
    );

    $context = new PolarSubscriptionSyncContext(
        subscription: $subscription,
        syncPayload: $syncPayload,
    );

    expect($context->subscription)->toBe($subscription);
    expect($context->syncPayload)->toBe($syncPayload);
});

it('is a readonly class', function (): void {
    $reflection = new ReflectionClass(PolarSubscriptionSyncContext::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('is a final class', function (): void {
    $reflection = new ReflectionClass(PolarSubscriptionSyncContext::class);

    expect($reflection->isFinal())->toBeTrue();
});

it('preserves sync payload properties', function (): void {
    $subscription = (new Subscription)->forceFill(['id' => 2]);
    $plan = (new Plan)->forceFill(['id' => 5, 'tier' => 'pro']);
    $periodStart = CarbonImmutable::parse('2026-01-15');
    $periodEnd = CarbonImmutable::parse('2026-02-15');

    $syncPayload = new PolarSubscriptionSyncPayload(
        status: SubscriptionStatus::Trialing,
        plan: $plan,
        billingInterval: BillingInterval::Yearly,
        periodStart: $periodStart,
        periodEnd: $periodEnd,
        updateAttributes: ['billing_interval' => 'yearly', 'status' => 'trialing'],
    );

    $context = new PolarSubscriptionSyncContext(
        subscription: $subscription,
        syncPayload: $syncPayload,
    );

    expect($context->syncPayload->status)->toBe(SubscriptionStatus::Trialing);
    expect($context->syncPayload->plan)->toBe($plan);
    expect($context->syncPayload->billingInterval)->toBe(BillingInterval::Yearly);
    expect($context->syncPayload->periodStart)->toBe($periodStart);
    expect($context->syncPayload->periodEnd)->toBe($periodEnd);
    expect($context->syncPayload->updateAttributes)->toBe(['billing_interval' => 'yearly', 'status' => 'trialing']);
});
