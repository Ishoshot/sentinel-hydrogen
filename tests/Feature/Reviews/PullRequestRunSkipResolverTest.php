<?php

declare(strict_types=1);

use App\Actions\Reviews\Resolvers\PullRequestRunSkipResolver;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns skip reason when repository has no workspace', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $repository->setRelation('workspace', null);

    $resolver = app(PullRequestRunSkipResolver::class);
    $result = $resolver->resolve($repository, null);

    expect($result->shouldSkip())->toBeTrue()
        ->and($result->skipReason)->toBe('Repository is not associated with any workspace.')
        ->and($result->workspace)->toBeNull();
});

it('returns requested skip reason when provided', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $resolver = app(PullRequestRunSkipResolver::class);
    $result = $resolver->resolve($repository, 'Auto-review disabled');

    expect($result->shouldSkip())->toBeTrue()
        ->and($result->skipReason)->toBe('Auto-review disabled')
        ->and($result->workspace)->not->toBeNull()
        ->and($result->planLimitTriggered)->toBeFalse();
});

it('returns no skip reason when run is allowed within plan limits', function (): void {
    $installation = Installation::factory()->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $resolver = app(PullRequestRunSkipResolver::class);
    $result = $resolver->resolve($repository, null);

    expect($result->shouldSkip())->toBeFalse()
        ->and($result->skipReason)->toBeNull()
        ->and($result->planLimitTriggered)->toBeFalse()
        ->and($result->workspace)->not->toBeNull();
});

it('returns plan limit skip reason when monthly run limit is exceeded', function (): void {
    $workspace = Workspace::factory()->create();
    $installation = Installation::factory()->create([
        'workspace_id' => $workspace->id,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $plan = $workspace->plan;
    $runsLimit = $plan->monthly_runs_limit;

    Run::factory($runsLimit)->forRepository($repository)->create();

    $resolver = app(PullRequestRunSkipResolver::class);
    $result = $resolver->resolve($repository, null);

    expect($result->shouldSkip())->toBeTrue()
        ->and($result->planLimitTriggered)->toBeTrue()
        ->and($result->skipReasonCode)->toBe('runs_limit')
        ->and($result->workspace->id)->toBe($workspace->id);
});

it('returns subscription inactive skip reason when subscription is not active', function (): void {
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Revoked,
    ]);
    $installation = Installation::factory()->create([
        'workspace_id' => $workspace->id,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $resolver = app(PullRequestRunSkipResolver::class);
    $result = $resolver->resolve($repository, null);

    expect($result->shouldSkip())->toBeTrue()
        ->and($result->planLimitTriggered)->toBeTrue()
        ->and($result->skipReasonCode)->toBe('subscription_inactive')
        ->and($result->shouldPostPlanLimitComment())->toBeTrue();
});

it('prioritizes requested skip reason over plan limit checks', function (): void {
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Revoked,
    ]);
    $installation = Installation::factory()->create([
        'workspace_id' => $workspace->id,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $resolver = app(PullRequestRunSkipResolver::class);
    $result = $resolver->resolve($repository, 'Draft pull request');

    expect($result->shouldSkip())->toBeTrue()
        ->and($result->skipReason)->toBe('Draft pull request')
        ->and($result->planLimitTriggered)->toBeFalse();
});
