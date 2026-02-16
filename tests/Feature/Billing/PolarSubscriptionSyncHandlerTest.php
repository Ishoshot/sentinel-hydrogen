<?php

declare(strict_types=1);

use App\Actions\Billing\Handlers\PolarSubscriptionSyncHandler;
use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncContext;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\Log;

it('syncs subscription and workspace state from webhook payload', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    $polarSubId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $illuminatePlan->id,
        'polar_subscription_id' => $polarSubId,
        'status' => SubscriptionStatus::PastDue,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionActive,
        data: [
            'id' => $polarSubId,
            'status' => 'active',
            'product' => [
                'metadata' => ['plan_tier' => PlanTier::Illuminate->value],
            ],
            'current_period_start' => '2026-02-01T00:00:00Z',
            'current_period_end' => '2026-03-01T00:00:00Z',
            'recurring_interval' => 'monthly',
        ],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, SubscriptionStatus::Active);

    expect($result)->toBeInstanceOf(PolarSubscriptionSyncContext::class)
        ->and($result->subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($result->syncPayload->status)->toBe(SubscriptionStatus::Active)
        ->and($result->syncPayload->billingInterval)->toBe(BillingInterval::Monthly);

    $workspace->refresh();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($workspace->plan_id)->toBe($illuminatePlan->id);
});

it('returns null when subscription is not found in database', function (): void {
    Log::shouldReceive('warning')->atLeast()->once();

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionActive,
        data: [
            'id' => 'nonexistent_sub',
            'status' => 'active',
        ],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, SubscriptionStatus::Active);

    expect($result)->toBeNull();
});

it('returns null when webhook data is empty', function (): void {
    Log::shouldReceive('warning')->atLeast()->once();

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionActive,
        data: [],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, SubscriptionStatus::Active);

    expect($result)->toBeNull();
});

it('uses override status instead of polar status when provided', function (): void {
    $plan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    $polarSubId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'polar_subscription_id' => $polarSubId,
        'status' => SubscriptionStatus::PastDue,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionActive,
        data: [
            'id' => $polarSubId,
            'status' => 'past_due',
            'current_period_start' => '2026-02-01T00:00:00Z',
            'current_period_end' => '2026-03-01T00:00:00Z',
        ],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, SubscriptionStatus::Active);

    expect($result)->toBeInstanceOf(PolarSubscriptionSyncContext::class)
        ->and($result->syncPayload->status)->toBe(SubscriptionStatus::Active);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('falls back to polar status when override status is null', function (): void {
    $plan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $polarSubId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'polar_subscription_id' => $polarSubId,
        'status' => SubscriptionStatus::Active,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: [
            'id' => $polarSubId,
            'status' => 'past_due',
        ],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, null);

    expect($result)->toBeInstanceOf(PolarSubscriptionSyncContext::class)
        ->and($result->syncPayload->status)->toBe(SubscriptionStatus::PastDue);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue);
});

it('updates workspace plan when product metadata includes plan_tier', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $orchestratePlan = Plan::factory()->orchestrate()->create();

    $workspace = Workspace::factory()->create([
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $polarSubId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $illuminatePlan->id,
        'polar_subscription_id' => $polarSubId,
        'status' => SubscriptionStatus::Active,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: [
            'id' => $polarSubId,
            'status' => 'active',
            'product' => [
                'metadata' => ['plan_tier' => PlanTier::Orchestrate->value],
            ],
            'recurring_interval' => 'yearly',
        ],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, null);

    expect($result)->toBeInstanceOf(PolarSubscriptionSyncContext::class)
        ->and($result->syncPayload->plan->id)->toBe($orchestratePlan->id);

    $workspace->refresh();
    expect($workspace->plan_id)->toBe($orchestratePlan->id);
});

it('preserves subscription period dates from webhook', function (): void {
    $plan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $polarSubId = 'polar_sub_'.fake()->uuid();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'polar_subscription_id' => $polarSubId,
        'status' => SubscriptionStatus::Active,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: [
            'id' => $polarSubId,
            'status' => 'active',
            'current_period_start' => '2026-03-01T00:00:00Z',
            'current_period_end' => '2026-04-01T00:00:00Z',
        ],
    );

    $result = app(PolarSubscriptionSyncHandler::class)->sync($webhook, null);

    expect($result)->toBeInstanceOf(PolarSubscriptionSyncContext::class)
        ->and($result->syncPayload->periodStart->toIso8601String())->toBe('2026-03-01T00:00:00+00:00')
        ->and($result->syncPayload->periodEnd->toIso8601String())->toBe('2026-04-01T00:00:00+00:00');

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->current_period_start->toDateString())->toBe('2026-03-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-04-01');
});
