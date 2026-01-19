<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Enums\RunStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\PlanLimitEnforcer;

it('blocks runs when the monthly limit is reached', function (): void {
    $plan = Plan::factory()->create([
        'monthly_runs_limit' => 1,
    ]);

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
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
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
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
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
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
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
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
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $orchestratePlan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
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
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);

    // One free workspace
    Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);

    $enforcer = app(PlanLimitEnforcer::class);
    $result = $enforcer->ensureCanCreateWorkspace($user);

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('paid_plan_required');
});
