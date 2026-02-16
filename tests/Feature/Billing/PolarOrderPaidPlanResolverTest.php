<?php

declare(strict_types=1);

use App\Actions\Billing\Resolvers\PolarOrderPaidPlanResolver;
use App\Actions\Billing\ValueObjects\PolarOrderPaidPayload;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Models\Workspace;

it('resolves plan from the resolved plan tier string', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create(['plan_id' => $illuminatePlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: ['id' => 'order_1'],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: PlanTier::Illuminate->value,
        promotionId: null,
        billingInterval: BillingInterval::Monthly,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, PlanTier::Illuminate->value);

    expect($result)->not->toBeNull()
        ->and($result->tier)->toBe(PlanTier::Illuminate->value);
});

it('falls back to product metadata plan_tier when resolved tier is null', function (): void {
    $orchestratePlan = Plan::factory()->orchestrate()->create();
    $workspace = Workspace::factory()->create(['plan_id' => $orchestratePlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: [
            'id' => 'order_2',
            'product' => [
                'metadata' => ['plan_tier' => PlanTier::Orchestrate->value],
            ],
        ],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: BillingInterval::Monthly,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, null);

    expect($result)->not->toBeNull()
        ->and($result->tier)->toBe(PlanTier::Orchestrate->value);
});

it('falls back to workspace current plan when no tier can be resolved', function (): void {
    $sanctumPlan = Plan::factory()->sanctum()->create();
    $workspace = Workspace::factory()->create(['plan_id' => $sanctumPlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: ['id' => 'order_3'],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: null,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, null);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($sanctumPlan->id);
});

it('prefers resolved tier over product metadata tier', function (): void {
    Plan::factory()->illuminate()->create();
    Plan::factory()->orchestrate()->create();

    $workspace = Workspace::factory()->create();

    $payload = new PolarOrderPaidPayload(
        order: [
            'id' => 'order_4',
            'product' => [
                'metadata' => ['plan_tier' => PlanTier::Orchestrate->value],
            ],
        ],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: PlanTier::Illuminate->value,
        promotionId: null,
        billingInterval: BillingInterval::Monthly,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, PlanTier::Illuminate->value);

    expect($result)->not->toBeNull()
        ->and($result->tier)->toBe(PlanTier::Illuminate->value);
});

it('returns workspace plan when product metadata has non-array product', function (): void {
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $workspace = Workspace::factory()->create(['plan_id' => $foundationPlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: [
            'id' => 'order_5',
            'product' => 'not-an-array',
        ],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: null,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, null);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($foundationPlan->id);
});

it('returns workspace plan when product metadata has non-string plan_tier', function (): void {
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $workspace = Workspace::factory()->create(['plan_id' => $foundationPlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: [
            'id' => 'order_6',
            'product' => [
                'metadata' => ['plan_tier' => 12345],
            ],
        ],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: null,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, null);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($foundationPlan->id);
});

it('returns workspace plan when product metadata has invalid plan_tier string', function (): void {
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $workspace = Workspace::factory()->create(['plan_id' => $foundationPlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: [
            'id' => 'order_7',
            'product' => [
                'metadata' => ['plan_tier' => 'nonexistent_tier'],
            ],
        ],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: null,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, null);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($foundationPlan->id);
});

it('returns workspace plan when product has empty metadata', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create(['plan_id' => $illuminatePlan->id]);

    $payload = new PolarOrderPaidPayload(
        order: [
            'id' => 'order_8',
            'product' => [
                'metadata' => [],
            ],
        ],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: null,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, null);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($illuminatePlan->id);
});

it('returns null when resolved tier is invalid and no product metadata or workspace plan exists', function (): void {
    $workspace = Workspace::factory()->create(['plan_id' => null]);

    $payload = new PolarOrderPaidPayload(
        order: ['id' => 'order_9'],
        subscriptionData: null,
        subscriptionId: null,
        customerId: null,
        workspaceId: (string) $workspace->id,
        planTier: null,
        promotionId: null,
        billingInterval: null,
    );

    $result = app(PolarOrderPaidPlanResolver::class)->resolve($workspace, $payload, 'invalid_tier');

    expect($result)->toBeNull();
});
