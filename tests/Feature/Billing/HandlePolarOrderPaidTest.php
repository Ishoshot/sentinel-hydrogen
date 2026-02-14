<?php

declare(strict_types=1);

use App\Actions\Billing\HandlePolarOrderPaid;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

it('applies paid order metadata to workspace and subscription state', function (): void {
    $foundationPlan = Plan::factory()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: [
            'id' => 'order_123',
            'created_at' => '2026-02-14T12:00:00Z',
            'metadata' => [
                'workspace_id' => (string) $workspace->id,
                'plan_tier' => 'illuminate',
            ],
            'subscription' => [
                'id' => 'sub_123',
                'current_period_start' => '2026-02-01T00:00:00Z',
                'current_period_end' => '2026-03-01T00:00:00Z',
                'recurring_interval' => 'monthly',
            ],
            'customer' => [
                'id' => 'cus_123',
            ],
        ],
    );

    app(HandlePolarOrderPaid::class)->handle($webhook);

    $workspace->refresh();
    $workspacePlan = $workspace->plan;

    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($workspacePlan)->not->toBeNull()
        ->and($workspacePlan?->tier)->toBe('illuminate');

    $subscription = Subscription::query()
        ->where('polar_subscription_id', 'sub_123')
        ->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription?->workspace_id)->toBe($workspace->id)
        ->and($subscription?->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription?->billing_interval)->toBe(BillingInterval::Monthly)
        ->and($subscription?->current_period_start?->toIso8601String())->toBe('2026-02-01T00:00:00+00:00')
        ->and($subscription?->current_period_end?->toIso8601String())->toBe('2026-03-01T00:00:00+00:00');
});

it('resolves workspace and plan tier from existing subscription when metadata is missing', function (): void {
    $orchestratePlan = Plan::factory()->orchestrate()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $orchestratePlan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $orchestratePlan->id,
        'polar_subscription_id' => 'sub_existing',
        'polar_customer_id' => 'cus_existing',
        'status' => SubscriptionStatus::PastDue,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: [
            'id' => 'order_existing',
            'subscription_id' => 'sub_existing',
            'customer_id' => 'cus_existing',
            'created_at' => '2026-02-14T12:00:00Z',
        ],
    );

    app(HandlePolarOrderPaid::class)->handle($webhook);

    $workspace->refresh();
    $subscription = Subscription::query()
        ->where('polar_subscription_id', 'sub_existing')
        ->first();

    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($workspace->plan?->tier)->toBe('orchestrate')
        ->and($subscription)->not->toBeNull()
        ->and($subscription?->workspace_id)->toBe($workspace->id)
        ->and($subscription?->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription?->polar_customer_id)->toBe('cus_existing');
});

it('updates workspace plan without creating subscription when order lacks subscription identity', function (): void {
    $foundationPlan = Plan::factory()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::OrderPaid,
        data: [
            'id' => 'order_workspace_only',
            'metadata' => [
                'workspace_id' => (string) $workspace->id,
                'plan_tier' => 'sanctum',
            ],
        ],
    );

    app(HandlePolarOrderPaid::class)->handle($webhook);

    $workspace->refresh();

    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($workspace->plan?->tier)->toBe('sanctum')
        ->and(
            Subscription::query()
                ->where('workspace_id', $workspace->id)
                ->count()
        )->toBe(0);
});
