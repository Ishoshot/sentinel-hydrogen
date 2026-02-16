<?php

declare(strict_types=1);

use App\Actions\Billing\HandlePolarOrderRefunded;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

it('revokes subscription on order refund', function (): void {
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $paidPlan = Plan::factory()->create(['tier' => 'illuminate']);
    $workspace = Workspace::factory()->create([
        'plan_id' => $paidPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);
    $subscription = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $paidPlan->id,
        'polar_subscription_id' => 'sub_123',
        'status' => SubscriptionStatus::Active,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderRefunded,
        data: [
            'id' => 'order_123',
            'subscription' => ['id' => 'sub_123'],
        ],
    );

    app(HandlePolarOrderRefunded::class)->handle($webhook);

    $subscription->refresh();
    $workspace->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Revoked);
    expect($subscription->ends_at)->not->toBeNull();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Revoked);
});

it('handles empty data gracefully', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderRefunded,
        data: [],
    );

    app(HandlePolarOrderRefunded::class)->handle($webhook);

    expect(true)->toBeTrue();
});

it('handles non-subscription order refund', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderRefunded,
        data: [
            'id' => 'order_123',
        ],
    );

    app(HandlePolarOrderRefunded::class)->handle($webhook);

    expect(true)->toBeTrue();
});

it('handles missing subscription in database', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderRefunded,
        data: [
            'id' => 'order_123',
            'subscription' => ['id' => 'sub_nonexistent'],
        ],
    );

    app(HandlePolarOrderRefunded::class)->handle($webhook);

    expect(true)->toBeTrue();
});

it('handles subscription_id directly in order data', function (): void {
    $plan = Plan::factory()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);
    $subscription = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'polar_subscription_id' => 'sub_456',
        'status' => SubscriptionStatus::Active,
    ]);

    Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderRefunded,
        data: [
            'id' => 'order_456',
            'subscription_id' => 'sub_456',
        ],
    );

    app(HandlePolarOrderRefunded::class)->handle($webhook);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Revoked);
});
