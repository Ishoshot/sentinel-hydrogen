<?php

declare(strict_types=1);

use App\Actions\Billing\HandlePolarSubscriptionEvent;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

it('routes active event to sync handler and updates subscription status to active', function (): void {
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
            'status' => 'active',
            'product' => [
                'metadata' => ['plan_tier' => PlanTier::Illuminate->value],
            ],
            'current_period_start' => '2026-02-01T00:00:00Z',
            'current_period_end' => '2026-03-01T00:00:00Z',
            'recurring_interval' => 'monthly',
        ],
    );

    app(HandlePolarSubscriptionEvent::class)->active($webhook);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    $workspace->refresh();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('routes canceled event to lifecycle handler and marks subscription canceled with ends_at', function (): void {
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

    $periodEnd = CarbonImmutable::parse('2026-03-15T00:00:00Z');

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCanceled,
        data: [
            'id' => $polarSubId,
            'status' => 'canceled',
            'current_period_end' => $periodEnd->toIso8601String(),
        ],
    );

    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'Subscription canceled'));

    app(HandlePolarSubscriptionEvent::class)->canceled($webhook);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->ends_at)->not->toBeNull()
        ->and($subscription->ends_at->toDateString())->toBe('2026-03-15');
});

it('canceled event returns early when subscription is not found', function (): void {
    Log::shouldReceive('warning')->atLeast()->once();
    Log::shouldReceive('info')->never();

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCanceled,
        data: [
            'id' => 'nonexistent_sub_id',
            'status' => 'canceled',
            'current_period_end' => '2026-03-15T00:00:00Z',
        ],
    );

    app(HandlePolarSubscriptionEvent::class)->canceled($webhook);
});

it('routes uncanceled event to lifecycle handler and reactivates subscription', function (): void {
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
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->addDays(10),
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUncanceled,
        data: [
            'id' => $polarSubId,
            'status' => 'active',
        ],
    );

    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'Subscription uncanceled'));

    app(HandlePolarSubscriptionEvent::class)->uncanceled($webhook);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->ends_at)->toBeNull();

    $workspace->refresh();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('uncanceled event returns early when subscription is not found', function (): void {
    Log::shouldReceive('warning')->atLeast()->once();
    Log::shouldReceive('info')->never();

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUncanceled,
        data: [
            'id' => 'nonexistent_sub_id',
            'status' => 'active',
        ],
    );

    app(HandlePolarSubscriptionEvent::class)->uncanceled($webhook);
});

it('routes revoked event to lifecycle handler and downgrades workspace', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();
    Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

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
        type: PolarWebhookEvent::SubscriptionRevoked,
        data: [
            'id' => $polarSubId,
            'status' => 'revoked',
        ],
    );

    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'Subscription revoked'));

    app(HandlePolarSubscriptionEvent::class)->revoked($webhook);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Revoked)
        ->and($subscription->ends_at)->not->toBeNull();

    $workspace->refresh();
    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Revoked)
        ->and($workspace->plan->tier)->toBe(PlanTier::Foundation->value);
});

it('revoked event returns early when subscription is not found', function (): void {
    Log::shouldReceive('warning')->atLeast()->once();
    Log::shouldReceive('info')->never();

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionRevoked,
        data: [
            'id' => 'nonexistent_sub_id',
            'status' => 'revoked',
        ],
    );

    app(HandlePolarSubscriptionEvent::class)->revoked($webhook);
});

it('routes updated event to sync handler and updates subscription attributes', function (): void {
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
        'billing_interval' => BillingInterval::Monthly,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: [
            'id' => $polarSubId,
            'status' => 'active',
            'product' => [
                'metadata' => ['plan_tier' => PlanTier::Orchestrate->value],
            ],
            'current_period_start' => '2026-02-15T00:00:00Z',
            'current_period_end' => '2027-02-15T00:00:00Z',
            'recurring_interval' => 'yearly',
        ],
    );

    app(HandlePolarSubscriptionEvent::class)->updated($webhook);

    $subscription = Subscription::query()
        ->where('polar_subscription_id', $polarSubId)
        ->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_id)->toBe($orchestratePlan->id)
        ->and($subscription->billing_interval)->toBe(BillingInterval::Yearly);

    $workspace->refresh();
    expect($workspace->plan_id)->toBe($orchestratePlan->id);
});

it('created event only logs debug and does not modify any state', function (): void {
    $polarSubId = 'polar_sub_'.fake()->uuid();

    Log::shouldReceive('debug')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'Subscription created'));

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCreated,
        data: [
            'id' => $polarSubId,
            'status' => 'incomplete',
        ],
    );

    app(HandlePolarSubscriptionEvent::class)->created($webhook);
});

it('active event returns early when subscription is not found in database', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionActive,
        data: [
            'id' => 'nonexistent_sub_id',
            'status' => 'active',
        ],
    );

    Log::shouldReceive('warning')->atLeast()->once();
    Log::shouldReceive('info')->never();

    app(HandlePolarSubscriptionEvent::class)->active($webhook);
});

it('active event returns early when webhook data is empty', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionActive,
        data: [],
    );

    Log::shouldReceive('warning')->atLeast()->once();
    Log::shouldReceive('info')->never();

    app(HandlePolarSubscriptionEvent::class)->active($webhook);
});
