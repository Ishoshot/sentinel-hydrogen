<?php

declare(strict_types=1);

use App\Actions\Reviews\Handlers\ReviewRunFinalizationHandler;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Enums\SentinelConfig\SentinelConfigTone;
use App\Exceptions\NoProviderKeyException;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use Illuminate\Http\Client\ConnectionException;

function makeReviewPolicy(array $overrides = []): ReviewPolicy
{
    return new ReviewPolicy(
        severityThresholds: $overrides['severityThresholds'] ?? ['comment' => 'info'],
        commentLimits: $overrides['commentLimits'] ?? ['max_inline_comments' => 25],
        enabledRules: $overrides['enabledRules'] ?? [],
        tone: $overrides['tone'] ?? SentinelConfigTone::Constructive,
        language: $overrides['language'] ?? 'en',
        focus: $overrides['focus'] ?? [],
        ignoredPaths: $overrides['ignoredPaths'] ?? [],
        annotations: $overrides['annotations'] ?? [],
        provider: $overrides['provider'] ?? [],
        configSource: $overrides['configSource'] ?? 'default',
    );
}

it('marks a run as skipped with reason and message', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $handler = new ReviewRunFinalizationHandler;
    $result = $handler->markSkippedWithReason($run, SkipReason::PlanLimitReached, 'Monthly limit exceeded');

    expect($result->status)->toBe(RunStatus::Skipped)
        ->and($result->completed_at)->not->toBeNull()
        ->and($result->metadata['skip_reason'])->toBe('plan_limit_reached')
        ->and($result->metadata['skip_message'])->toBe('Monthly limit exceeded');
});

it('preserves existing metadata when marking as skipped', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['pull_request_number' => 42, 'repository_full_name' => 'acme/repo'],
    ]);

    $handler = new ReviewRunFinalizationHandler;
    $result = $handler->markSkippedWithReason($run, SkipReason::OrphanedRepository, 'Repo has no workspace');

    expect($result->metadata['pull_request_number'])->toBe(42)
        ->and($result->metadata['repository_full_name'])->toBe('acme/repo')
        ->and($result->metadata['skip_reason'])->toBe('orphaned_repository')
        ->and($result->metadata['skip_message'])->toBe('Repo has no workspace');
});

it('marks a run as skipped for missing provider keys', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => now()->subMinutes(2),
    ]);

    $policy = makeReviewPolicy();
    $exception = NoProviderKeyException::noProvidersConfigured();

    $handler = new ReviewRunFinalizationHandler;
    $result = $handler->markSkippedNoProviderKeys($run, $policy, $exception);

    expect($result->status)->toBe(RunStatus::Skipped)
        ->and($result->completed_at)->not->toBeNull()
        ->and($result->metadata['skip_reason'])->toBe('no_provider_keys')
        ->and($result->metadata['skip_message'])->toBe('No provider keys configured for this repository')
        ->and($result->policy_snapshot)->toBe($policy->toArray())
        ->and($result->duration_seconds)->not->toBeNull();
});

it('marks a run as failed with exception details', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => now()->subSeconds(30),
    ]);

    $policy = makeReviewPolicy();
    $exception = new RuntimeException('API call timed out');

    $handler = new ReviewRunFinalizationHandler;
    $handler->markFailed($run, $policy, $exception);

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->metadata['review_failure']['message'])->toBe('API call timed out')
        ->and($run->metadata['review_failure']['type'])->toBe(RuntimeException::class)
        ->and($run->policy_snapshot)->toBe($policy->toArray())
        ->and($run->duration_seconds)->toBeGreaterThanOrEqual(0);
});

it('calculates duration as null when started_at is null', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => null,
    ]);

    $policy = makeReviewPolicy();
    $exception = new RuntimeException('Failure');

    $handler = new ReviewRunFinalizationHandler;
    $handler->markFailed($run, $policy, $exception);

    $run->refresh();

    expect($run->duration_seconds)->toBeNull();
});

it('maps connection exception to connection error label', function (): void {
    $handler = new ReviewRunFinalizationHandler;

    $result = $handler->simpleErrorType(new ConnectionException('Connection refused'));

    expect($result)->toBe('Connection Error');
});

it('maps validation exception to validation error label', function (): void {
    $handler = new ReviewRunFinalizationHandler;

    $result = $handler->simpleErrorType(new Illuminate\Validation\ValidationException(
        Illuminate\Support\Facades\Validator::make([], ['field' => 'required'])
    ));

    expect($result)->toBe('Validation Error');
});

it('maps unknown exceptions to internal error label', function (): void {
    $handler = new ReviewRunFinalizationHandler;

    $result = $handler->simpleErrorType(new RuntimeException('Something broke'));

    expect($result)->toBe('Internal Error');
});

it('maps no provider key exception to internal error label', function (): void {
    $handler = new ReviewRunFinalizationHandler;

    $result = $handler->simpleErrorType(NoProviderKeyException::noProvidersConfigured());

    expect($result)->toBe('Internal Error');
});

it('persists skipped run to database', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $handler = new ReviewRunFinalizationHandler;
    $handler->markSkippedWithReason($run, SkipReason::InstallationInactive, 'Installation suspended');

    $persisted = Run::find($run->id);

    expect($persisted->status)->toBe(RunStatus::Skipped)
        ->and($persisted->metadata['skip_reason'])->toBe('installation_inactive');
});

it('stores policy snapshot when marking as failed', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => now()->subMinutes(1),
    ]);

    $policy = makeReviewPolicy([
        'severityThresholds' => ['comment' => 'high'],
        'enabledRules' => ['security', 'performance'],
        'configSource' => 'repository',
    ]);

    $handler = new ReviewRunFinalizationHandler;
    $handler->markFailed($run, $policy, new RuntimeException('Test'));

    $run->refresh();

    expect($run->policy_snapshot['severity_thresholds']['comment'])->toBe('high')
        ->and($run->policy_snapshot['enabled_rules'])->toBe(['security', 'performance'])
        ->and($run->policy_snapshot['config_source'])->toBe('repository');
});

it('handles each skip reason correctly', function (SkipReason $reason): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $handler = new ReviewRunFinalizationHandler;
    $result = $handler->markSkippedWithReason($run, $reason, 'Test message');

    expect($result->metadata['skip_reason'])->toBe($reason->value);
})->with([
    'no provider keys' => [SkipReason::NoProviderKeys],
    'run failed' => [SkipReason::RunFailed],
    'plan limit reached' => [SkipReason::PlanLimitReached],
    'orphaned repository' => [SkipReason::OrphanedRepository],
    'installation inactive' => [SkipReason::InstallationInactive],
]);
