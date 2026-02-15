<?php

declare(strict_types=1);

use App\Actions\Billing\Handlers\PolarSubscriptionStateHandler;
use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncPayload;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

it('marks subscription as canceled', function (): void {
    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
    ]);

    $handler = new PolarSubscriptionStateHandler;
    $endsAt = CarbonImmutable::parse('2026-03-01');
    $handler->markCanceled($subscription, $endsAt);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);
    expect($subscription->ends_at)->not->toBeNull();
});

it('marks subscription as uncanceled and updates workspace', function (): void {
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);
    $subscription = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->addMonth(),
    ]);

    $handler = new PolarSubscriptionStateHandler;
    $handler->markUncanceled($subscription);

    $subscription->refresh();
    $workspace->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->ends_at)->toBeNull();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('marks subscription as revoked and downgrades workspace', function (): void {
    $paidPlan = Plan::factory()->create();
    $foundationPlan = Plan::factory()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $paidPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);
    $subscription = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $handler = new PolarSubscriptionStateHandler;
    $handler->markRevoked($subscription, $foundationPlan);

    $subscription->refresh();
    $workspace->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Revoked);
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Revoked);
    expect($workspace->plan_id)->toBe($foundationPlan->id);
});

it('syncs subscription from payload', function (): void {
    $plan = Plan::factory()->create();
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);
    $subscription = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => SubscriptionStatus::PastDue,
    ]);

    $syncPayload = new PolarSubscriptionSyncPayload(
        status: SubscriptionStatus::Active,
        plan: $plan,
        billingInterval: null,
        periodStart: null,
        periodEnd: null,
        updateAttributes: ['status' => SubscriptionStatus::Active, 'plan_id' => $plan->id],
    );

    $handler = new PolarSubscriptionStateHandler;
    $handler->syncSubscription($subscription, $syncPayload);

    $subscription->refresh();
    $workspace->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
    expect($workspace->plan_id)->toBe($plan->id);
});
