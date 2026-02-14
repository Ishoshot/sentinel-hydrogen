<?php

declare(strict_types=1);

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->foundationPlan = Plan::factory()->create([
        'tier' => PlanTier::Foundation->value,
    ]);

    $this->illuminatePlan = Plan::factory()->illuminate()->create();
});

it('downgrades expired canceled subscriptions to Foundation', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => $this->illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $this->illuminatePlan->id,
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:expire-canceled')
        ->assertSuccessful();

    $workspace->refresh();

    expect($workspace->plan_id)->toBe($this->foundationPlan->id)
        ->and($workspace->subscription_status)->toBe(SubscriptionStatus::Revoked);

    expect(Subscription::query()->where('workspace_id', $workspace->id)->first())
        ->status->toBe(SubscriptionStatus::Revoked);
});

it('does not downgrade canceled subscriptions with future ends_at', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => $this->illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $this->illuminatePlan->id,
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->addWeek(),
    ]);

    $this->artisan('subscriptions:expire-canceled')
        ->assertSuccessful();

    $workspace->refresh();

    expect($workspace->plan_id)->toBe($this->illuminatePlan->id)
        ->and($workspace->subscription_status)->toBe(SubscriptionStatus::Canceled);
});

it('skips already-revoked subscriptions', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => $this->foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Revoked,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $this->illuminatePlan->id,
        'status' => SubscriptionStatus::Revoked,
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:expire-canceled')
        ->assertSuccessful();

    $workspace->refresh();

    expect($workspace->plan_id)->toBe($this->foundationPlan->id);
});

it('skips Foundation-tier workspaces', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => $this->foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $this->foundationPlan->id,
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:expire-canceled')
        ->assertSuccessful()
        ->expectsOutputToContain('Done. 0 expired subscription(s) downgraded to Foundation.');
});

it('outputs the summary count', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => $this->illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $this->illuminatePlan->id,
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->subDays(2),
    ]);

    $this->artisan('subscriptions:expire-canceled')
        ->expectsOutputToContain('Done. 1 expired subscription(s) downgraded to Foundation.')
        ->assertSuccessful();
});

it('logs an activity for each expired subscription', function (): void {
    $workspace = Workspace::factory()->create([
        'plan_id' => $this->illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    $subscription = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $this->illuminatePlan->id,
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:expire-canceled')
        ->assertSuccessful();

    $activity = Activity::query()
        ->where('workspace_id', $workspace->id)
        ->where('type', ActivityType::SubscriptionExpired->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Subscription expired and workspace downgraded to Foundation plan.')
        ->and($activity->subject_type)->toBe(Subscription::class)
        ->and($activity->subject_id)->toBe($subscription->id);
});
