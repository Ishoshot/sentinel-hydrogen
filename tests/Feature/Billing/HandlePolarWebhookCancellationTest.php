<?php

declare(strict_types=1);

use App\Actions\Billing\HandlePolarSubscriptionEvent;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Carbon\CarbonImmutable;

it('keeps workspace active when subscription is canceled', function (): void {
    $plan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $polarSubscriptionId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'polar_subscription_id' => $polarSubscriptionId,
        'status' => SubscriptionStatus::Active,
    ]);

    $periodEnd = CarbonImmutable::now()->addDays(15)->toIso8601String();

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCanceled,
        data: [
            'id' => $polarSubscriptionId,
            'status' => 'canceled',
            'current_period_end' => $periodEnd,
        ],
    );

    $action = app(HandlePolarSubscriptionEvent::class);
    $action->canceled($webhook);

    // Workspace should remain Active
    $workspace->refresh();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);

    // Subscription record should be Canceled with ends_at set
    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubscriptionId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->ends_at)->not->toBeNull();
});

it('marks subscription record as canceled with ends_at from webhook', function (): void {
    $plan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $polarSubscriptionId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'polar_subscription_id' => $polarSubscriptionId,
        'status' => SubscriptionStatus::Active,
    ]);

    $periodEnd = CarbonImmutable::parse('2026-03-15T00:00:00Z');

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCanceled,
        data: [
            'id' => $polarSubscriptionId,
            'status' => 'canceled',
            'current_period_end' => $periodEnd->toIso8601String(),
        ],
    );

    $action = app(HandlePolarSubscriptionEvent::class);
    $action->canceled($webhook);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubscriptionId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->ends_at->toDateString())->toBe('2026-03-15');
});
