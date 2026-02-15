<?php

declare(strict_types=1);

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Plans\Resolvers\PlanResolver;

beforeEach(function (): void {
    $this->resolver = new PlanResolver();
});

it('returns the existing plan when workspace already has one', function (): void {
    $plan = Plan::factory()->illuminate()->create();
    $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);

    $result = $this->resolver->resolve($workspace);

    expect($result->id)->toBe($plan->id)
        ->and($result->tier)->toBe(PlanTier::Illuminate->value);
});

it('creates and assigns the foundation plan when workspace has no plan', function (): void {
    $workspace = Workspace::factory()->create(['plan_id' => null]);

    $result = $this->resolver->resolve($workspace);

    expect($result)->toBeInstanceOf(Plan::class)
        ->and($result->tier)->toBe(PlanTier::Foundation->value);

    $workspace->refresh();

    expect($workspace->plan_id)->toBe($result->id)
        ->and($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('reuses existing foundation plan when creating for second workspace', function (): void {
    $workspace1 = Workspace::factory()->create(['plan_id' => null]);
    $workspace2 = Workspace::factory()->create(['plan_id' => null]);

    $plan1 = $this->resolver->resolve($workspace1);
    $plan2 = $this->resolver->resolve($workspace2);

    expect($plan1->id)->toBe($plan2->id);
});

it('preserves existing subscription status when assigning plan', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => null,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->resolver->resolve($workspace);

    $workspace->refresh();

    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('assigns active subscription status when creating default plan', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => null,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->resolver->resolve($workspace);

    $workspace->refresh();

    expect($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('does not modify workspace when plan already exists', function (): void {
    $plan = Plan::factory()->orchestrate()->create();
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $originalUpdatedAt = $workspace->updated_at;

    $this->resolver->resolve($workspace);

    $workspace->refresh();

    expect($workspace->plan_id)->toBe($plan->id)
        ->and($workspace->updated_at->toDateTimeString())->toBe($originalUpdatedAt->toDateTimeString());
});
