<?php

declare(strict_types=1);

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\Guards\ReviewRunSupersededGuard;
use App\Actions\Reviews\HandlePullRequestWebhook;
use App\Actions\Reviews\SupersedeActiveRuns;
use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    mock(PostsSkipReasonComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    mock(PostsAutoReviewDisabledComment::class)
        ->shouldReceive('handle')
        ->andReturnNull();

    app()->instance(FetchesSentinelConfig::class, new class implements FetchesSentinelConfig
    {
        public function handle(Repository $repository, ?string $ref = null): array
        {
            return ['found' => false, 'content' => null, 'sha' => null, 'error' => null];
        }
    });
});

/**
 * Build a standard PR webhook payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function buildPullRequestPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'action' => 'synchronize',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Test PR',
            'body' => 'Test body',
            'draft' => false,
            'user' => ['login' => 'testuser', 'avatar_url' => null],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'new-sha'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ], $overrides);
}

/**
 * Create a repository with auto-review enabled for webhook tests.
 */
function createAutoReviewRepository(): Repository
{
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'github_id' => 987654,
        'full_name' => 'org/repo',
        'name' => 'repo',
    ]);
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create();

    return $repository;
}

/**
 * Process a pull request webhook with standard fakes.
 *
 * @param  array<string, mixed>  $payload
 */
function dispatchPullRequestWebhook(array $payload): void
{
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            return 12345;
        }
    };

    $fakeConfigError = new class implements PostsConfigErrorComment
    {
        public function handle(Repository $repository, int $pullRequestNumber, string $error): ?int
        {
            return null;
        }
    };

    $fakeAutoReviewDisabled = new class implements PostsAutoReviewDisabledComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            return null;
        }
    };

    app()->instance(PostsGreetingComment::class, $fakeGreeting);
    app()->instance(PostsConfigErrorComment::class, $fakeConfigError);
    app()->instance(PostsAutoReviewDisabledComment::class, $fakeAutoReviewDisabled);

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(app(HandlePullRequestWebhook::class));
}

// --- SupersedeActiveRuns unit tests ---

it('supersedes queued runs for the same pull request', function (): void {
    $repository = createAutoReviewRepository();

    $queuedRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:old-sha',
        'metadata' => ['pull_request_number' => 42, 'repository_full_name' => 'org/repo'],
    ]);

    app(SupersedeActiveRuns::class)->handle($repository, 42);

    $queuedRun->refresh();

    expect($queuedRun->status)->toBe(RunStatus::Skipped)
        ->and($queuedRun->completed_at)->not->toBeNull()
        ->and($queuedRun->metadata['skip_reason'])->toBe(SkipReason::Superseded->value)
        ->and($queuedRun->metadata['skip_message'])->toBe('A newer commit was pushed to this pull request.');
});

it('supersedes in-progress runs for the same pull request', function (): void {
    $repository = createAutoReviewRepository();

    $inProgressRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::InProgress,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:old-sha',
        'metadata' => ['pull_request_number' => 42, 'repository_full_name' => 'org/repo'],
    ]);

    app(SupersedeActiveRuns::class)->handle($repository, 42);

    $inProgressRun->refresh();

    expect($inProgressRun->status)->toBe(RunStatus::Skipped)
        ->and($inProgressRun->metadata['skip_reason'])->toBe(SkipReason::Superseded->value);
});

it('does not supersede completed runs', function (): void {
    $repository = createAutoReviewRepository();

    $completedRun = Run::factory()->forRepository($repository)->completed()->create([
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:old-sha',
    ]);

    app(SupersedeActiveRuns::class)->handle($repository, 42);

    $completedRun->refresh();

    expect($completedRun->status)->toBe(RunStatus::Completed);
});

it('does not supersede runs for a different pull request', function (): void {
    $repository = createAutoReviewRepository();

    $otherPrRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 99,
        'external_reference' => 'github:pull_request:99:some-sha',
    ]);

    app(SupersedeActiveRuns::class)->handle($repository, 42);

    $otherPrRun->refresh();

    expect($otherPrRun->status)->toBe(RunStatus::Queued);
});

it('supersedes multiple active runs at once', function (): void {
    $repository = createAutoReviewRepository();

    $firstRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:sha-1',
        'metadata' => ['pull_request_number' => 42, 'repository_full_name' => 'org/repo'],
    ]);
    $secondRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::InProgress,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:sha-2',
        'metadata' => ['pull_request_number' => 42, 'repository_full_name' => 'org/repo'],
    ]);

    $result = app(SupersedeActiveRuns::class)->handle($repository, 42);

    $firstRun->refresh();
    $secondRun->refresh();

    expect($result)->toHaveCount(2)
        ->and($firstRun->status)->toBe(RunStatus::Skipped)
        ->and($secondRun->status)->toBe(RunStatus::Skipped);
});

it('returns empty collection when no active runs exist', function (): void {
    $repository = createAutoReviewRepository();

    $result = app(SupersedeActiveRuns::class)->handle($repository, 42);

    expect($result)->toBeEmpty();
});

// --- ReviewRunSupersededGuard tests ---

it('guard marks only the current run as superseded when a newer run exists', function (): void {
    $repository = createAutoReviewRepository();

    $oldRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:old-sha',
        'metadata' => ['pull_request_number' => 42, 'repository_full_name' => 'org/repo'],
    ]);

    $newerRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:new-sha',
    ]);

    $result = app(ReviewRunSupersededGuard::class)->isSuperseded($oldRun);

    $oldRun->refresh();
    $newerRun->refresh();

    expect($result)->toBeTrue()
        ->and($oldRun->status)->toBe(RunStatus::Skipped)
        ->and($oldRun->metadata['skip_reason'])->toBe(SkipReason::Superseded->value)
        ->and($newerRun->status)->toBe(RunStatus::Queued);
});

it('guard returns false when no newer run exists', function (): void {
    $repository = createAutoReviewRepository();

    $latestRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:latest-sha',
    ]);

    $result = app(ReviewRunSupersededGuard::class)->isSuperseded($latestRun);

    $latestRun->refresh();

    expect($result)->toBeFalse()
        ->and($latestRun->status)->toBe(RunStatus::Queued);
});

// --- Integration: webhook flow supersedes existing runs ---

it('supersedes active runs when a new push arrives via webhook', function (): void {
    $repository = createAutoReviewRepository();

    Queue::fake();

    // Simulate an existing queued run from a previous push
    $existingRun = Run::factory()->forRepository($repository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:old-sha',
        'metadata' => [
            'pull_request_number' => 42,
            'repository_full_name' => 'org/repo',
        ],
    ]);

    // Process a new push (synchronize event) with a different SHA
    dispatchPullRequestWebhook(buildPullRequestPayload([
        'pull_request' => [
            'head' => ['ref' => 'feature', 'sha' => 'new-sha-abc'],
        ],
    ]));

    $existingRun->refresh();

    // The old run should be superseded
    expect($existingRun->status)->toBe(RunStatus::Skipped)
        ->and($existingRun->metadata['skip_reason'])->toBe(SkipReason::Superseded->value)
        ->and($existingRun->completed_at)->not->toBeNull();

    // A new run should be created and dispatched
    $newRun = Run::query()
        ->where('external_reference', 'github:pull_request:42:new-sha-abc')
        ->first();

    expect($newRun)->not->toBeNull()
        ->and($newRun->status)->toBe(RunStatus::Queued);

    Queue::assertPushed(ExecuteReviewRun::class, fn (ExecuteReviewRun $job): bool => $job->runId === $newRun->id);
});

it('does not supersede runs from a different repository', function (): void {
    $repository = createAutoReviewRepository();

    // Create a run for a different repository but same PR number
    $otherRepository = Repository::factory()->create();
    $otherRepoRun = Run::factory()->forRepository($otherRepository)->create([
        'status' => RunStatus::Queued,
        'pr_number' => 42,
        'external_reference' => 'github:pull_request:42:other-repo-sha',
    ]);

    Queue::fake();

    dispatchPullRequestWebhook(buildPullRequestPayload([
        'pull_request' => [
            'head' => ['ref' => 'feature', 'sha' => 'new-sha-xyz'],
        ],
    ]));

    $otherRepoRun->refresh();

    expect($otherRepoRun->status)->toBe(RunStatus::Queued);
});
