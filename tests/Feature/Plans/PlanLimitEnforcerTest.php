<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\RunStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\PlanLimitEnforcer;
use Carbon\CarbonImmutable;

it('blocks runs when the monthly limit is reached', function (): void {
    $plan = Plan::factory()->create([
        'monthly_runs_limit' => 1,
    ]);

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    Run::factory()
        ->forRepository($repository)
        ->create([
            'workspace_id' => $workspace->id,
            'status' => RunStatus::Completed,
        ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureRunAllowed($workspace);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('runs_limit');
});

it('blocks invitations when team size limit is reached', function (): void {
    $plan = Plan::factory()->create([
        'team_size_limit' => 1,
    ]);

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    $user = User::factory()->create();
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanInviteMember($workspace);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('team_size_limit');
});

it('allows first workspace creation for new users', function (): void {
    $user = User::factory()->create();

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanCreateWorkspace($user);

    expect($result->allowed)->toBeTrue();
});

it('blocks additional workspace creation when existing workspace is on free plan', function (): void {
    $foundationPlan = Plan::factory()->create(); // Foundation is free

    $user = User::factory()->create();
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanCreateWorkspace($user);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('paid_plan_required');
});

it('allows additional workspace creation when all existing workspaces are on paid plans', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $user = User::factory()->create();
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanCreateWorkspace($user);

    expect($result->allowed)->toBeTrue();
});

it('allows multiple workspaces when all are on paid plans', function (): void {
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $orchestratePlan = Plan::factory()->orchestrate()->create();

    $user = User::factory()->create();

    // Multiple workspaces on paid plans
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $orchestratePlan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanCreateWorkspace($user);

    expect($result->allowed)->toBeTrue();
});

it('blocks workspace creation when any existing workspace is on free plan', function (): void {
    $foundationPlan = Plan::factory()->create(); // Foundation is free
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $user = User::factory()->create();

    // One paid workspace
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    // One free workspace
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanCreateWorkspace($user);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('paid_plan_required');
});

it('enforces run limits using the subscription billing period, not calendar month', function (): void {
    $plan = Plan::factory()->create([
        'monthly_runs_limit' => 2,
    ]);

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    // Subscription period: Jan 15 – Feb 14
    $periodStart = CarbonImmutable::parse('2026-01-15');
    $periodEnd = CarbonImmutable::parse('2026-02-14');

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd,
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    // Create 2 runs within the subscription period (should hit limit)
    Run::factory()->count(2)->forRepository($repository)->create([
        'workspace_id' => $workspace->id,
        'status' => RunStatus::Completed,
        'created_at' => CarbonImmutable::parse('2026-01-20'),
    ]);

    // Create 1 run outside the subscription period (should not count)
    Run::factory()->forRepository($repository)->create([
        'workspace_id' => $workspace->id,
        'status' => RunStatus::Completed,
        'created_at' => CarbonImmutable::parse('2026-01-10'),
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureRunAllowed($workspace);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('runs_limit');
});

it('allows runs outside the subscription billing period even if calendar month has usage', function (): void {
    $plan = Plan::factory()->create([
        'monthly_runs_limit' => 1,
    ]);

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    // Subscription period: Feb 1 – Feb 28 (current billing period)
    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'current_period_start' => CarbonImmutable::parse('2026-02-01'),
        'current_period_end' => CarbonImmutable::parse('2026-02-28'),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    // Run from previous billing period (Jan) — should NOT count
    Run::factory()->forRepository($repository)->create([
        'workspace_id' => $workspace->id,
        'status' => RunStatus::Completed,
        'created_at' => CarbonImmutable::parse('2026-01-25'),
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureRunAllowed($workspace);

    expect($result->allowed)->toBeTrue();
});

it('denies paid workspace with no subscription record', function (): void {
    $plan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    // No Subscription record created

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureActiveSubscription($workspace);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('subscription_missing');
});

it('denies paid workspace with expired subscription period', function (): void {
    $plan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'current_period_start' => CarbonImmutable::parse('2025-12-01'),
        'current_period_end' => CarbonImmutable::parse('2025-12-31'),
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureActiveSubscription($workspace);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('subscription_expired');
});

it('allows foundation workspace without subscription record', function (): void {
    $plan = Plan::factory()->create(); // Foundation (free) tier

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\Billing\SubscriptionStatus::Active,
    ]);

    // No Subscription record -- that's OK for Foundation

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureActiveSubscription($workspace);

    expect($result->allowed)->toBeTrue();
});
