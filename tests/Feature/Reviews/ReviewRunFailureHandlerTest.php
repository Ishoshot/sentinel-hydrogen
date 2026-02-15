<?php

declare(strict_types=1);

use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\Handlers\ReviewRunFailureHandler;
use App\Actions\Reviews\Handlers\ReviewRunFinalizationHandler;
use App\Actions\Reviews\Loggers\ReviewRunActivityLogger;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Enums\SentinelConfig\SentinelConfigTone;
use App\Exceptions\NoProviderKeyException;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Reviews\ValueObjects\ReviewPolicy;
use Illuminate\Support\Facades\Log;

function makeTestPolicy(array $overrides = []): ReviewPolicy
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

it('marks a run as skipped with reason and posts a comment', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $postComment = Mockery::mock(PostsSkipReasonComment::class);
    $postComment->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Run $r, SkipReason $reason, ?string $detail = null): bool => $reason === SkipReason::PlanLimitReached
            && $detail === 'Monthly limit exceeded'
        )
        ->andReturnNull();

    $handler = new ReviewRunFailureHandler(
        finalizer: new ReviewRunFinalizationHandler,
        activityLogger: app(ReviewRunActivityLogger::class),
        postSkipReasonComment: $postComment,
    );

    $result = $handler->markSkippedWithReason($run, SkipReason::PlanLimitReached, 'Monthly limit exceeded');

    expect($result->status)->toBe(RunStatus::Skipped)
        ->and($result->metadata['skip_reason'])->toBe('plan_limit_reached')
        ->and($result->metadata['skip_message'])->toBe('Monthly limit exceeded');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Review run skipped'
            && $context['run_id'] === $run->id
            && $context['reason'] === 'plan_limit_reached'
        )->once();
});

it('marks a run as skipped for no provider keys and logs activity', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => now()->subMinutes(1),
        'metadata' => [
            'pull_request_number' => 99,
            'repository_full_name' => 'acme/repo',
        ],
    ]);

    $exception = NoProviderKeyException::noProvidersConfigured();

    $postComment = Mockery::mock(PostsSkipReasonComment::class);
    $postComment->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Run $r, SkipReason $reason): bool => $reason === SkipReason::NoProviderKeys)
        ->andReturnNull();

    $handler = new ReviewRunFailureHandler(
        finalizer: new ReviewRunFinalizationHandler,
        activityLogger: app(ReviewRunActivityLogger::class),
        postSkipReasonComment: $postComment,
    );

    $policy = makeTestPolicy();
    $result = $handler->markNoProviderKeys($run, $policy, $exception);

    expect($result->status)->toBe(RunStatus::Skipped)
        ->and($result->metadata['skip_reason'])->toBe('no_provider_keys');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Review run skipped - no BYOK provider keys configured'
            && $context['run_id'] === $run->id
            && $context['workspace_id'] === $workspace->id
            && $context['repository_id'] === $repository->id
        )->once();
});

it('marks a run as failed and logs error and activity', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => now()->subSeconds(10),
        'metadata' => [
            'pull_request_number' => 55,
            'repository_full_name' => 'acme/widget',
        ],
    ]);

    $exception = new RuntimeException('Provider API returned 500');

    $postComment = Mockery::mock(PostsSkipReasonComment::class);
    $postComment->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Run $r, SkipReason $reason, ?string $detail = null): bool => $reason === SkipReason::RunFailed
            && $detail === 'Internal Error'
        )
        ->andReturnNull();

    $handler = new ReviewRunFailureHandler(
        finalizer: new ReviewRunFinalizationHandler,
        activityLogger: app(ReviewRunActivityLogger::class),
        postSkipReasonComment: $postComment,
    );

    $policy = makeTestPolicy();
    $handler->markFailed($run, $policy, $exception);

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->metadata['review_failure']['message'])->toBe('Provider API returned 500')
        ->and($run->metadata['review_failure']['type'])->toBe(RuntimeException::class);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Review run failed'
            && $context['run_id'] === $run->id
            && $context['exception'] === 'Provider API returned 500'
        )->once();
});

it('delegates to real finalizer and returns the run', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $postComment = Mockery::mock(PostsSkipReasonComment::class);
    $postComment->shouldReceive('handle')
        ->once()
        ->andReturnNull();

    $handler = new ReviewRunFailureHandler(
        finalizer: new ReviewRunFinalizationHandler,
        activityLogger: app(ReviewRunActivityLogger::class),
        postSkipReasonComment: $postComment,
    );

    $result = $handler->markSkippedWithReason($run, SkipReason::InstallationInactive, 'Inactive');

    expect($result->id)->toBe($run->id)
        ->and($result->status)->toBe(RunStatus::Skipped)
        ->and($result->metadata['skip_reason'])->toBe('installation_inactive');
});

it('posts skip reason comment with error type on failure', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'started_at' => now(),
    ]);

    $postComment = Mockery::mock(PostsSkipReasonComment::class);
    $postComment->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Run $r, SkipReason $reason, ?string $detail = null): bool => $reason === SkipReason::RunFailed
            && is_string($detail)
        )
        ->andReturnNull();

    $handler = new ReviewRunFailureHandler(
        finalizer: new ReviewRunFinalizationHandler,
        activityLogger: app(ReviewRunActivityLogger::class),
        postSkipReasonComment: $postComment,
    );

    $handler->markFailed($run, makeTestPolicy(), new RuntimeException('Something broke'));
});
