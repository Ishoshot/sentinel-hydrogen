<?php

declare(strict_types=1);

use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\Reviews\CreatePullRequestRun;
use App\Actions\Reviews\SyncPullRequestRunMetadata;
use App\Enums\ProviderType;
use App\Enums\RunStatus;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Services\GitHub\GitHubWebhookService;
use App\Services\Queue\QueueResolver;
use App\Services\SentinelConfig\TriggerRuleEvaluator;
use Illuminate\Support\Facades\Queue;

/**
 * Create a fake config error poster that should not be called.
 */
function fakeConfigErrorPoster(): PostsConfigErrorComment
{
    return new class implements PostsConfigErrorComment
    {
        public function handle(Repository $repository, int $pullRequestNumber, string $error): ?int
        {
            throw new Exception('Config error poster should not be called');
        }
    };
}

it('creates a run for pull request webhook when auto review is enabled', function (): void {
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

    Queue::fake();

    $payload = [
        'action' => 'opened',
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
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Create a fake greeting action that returns a comment ID
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public bool $wasCalled = false;

        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            $this->wasCalled = true;

            return 12345;
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $run = Run::query()->first();

    expect($fakeGreeting->wasCalled)->toBeTrue()
        ->and($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Queued)
        ->and($run?->external_reference)->toBe('github:pull_request:42:abc123')
        ->and($run?->metadata['github_comment_id'])->toBe(12345);

    Queue::assertPushed(ExecuteReviewRun::class, fn (ExecuteReviewRun $job): bool => $job->runId === $run?->id);
});

it('skips run creation when auto review is disabled', function (): void {
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
    RepositorySettings::factory()->forRepository($repository)->autoReviewDisabled()->create();

    Queue::fake();

    $payload = [
        'action' => 'opened',
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
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Create a fake greeting action that should NOT be called
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called when auto-review is disabled');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    expect(Run::query()->count())->toBe(0);
    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('syncs metadata when labels are added to an existing run', function (): void {
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

    // Create an existing run
    $existingRun = Run::factory()->forRepository($repository)->create([
        'external_reference' => 'github:pull_request:42:abc123',
        'metadata' => [
            'pull_request_number' => 42,
            'pull_request_title' => 'Test PR',
            'labels' => [],
            'assignees' => [],
            'reviewers' => [],
        ],
    ]);

    Queue::fake();

    $payload = [
        'action' => 'labeled',
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
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [
                ['name' => 'bug', 'color' => 'd73a4a'],
            ],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Create a fake greeting action that should NOT be called for metadata sync
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called for metadata sync');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $existingRun->refresh();

    expect($existingRun->metadata['labels'])->toHaveCount(1)
        ->and($existingRun->metadata['labels'][0]['name'])->toBe('bug')
        ->and($existingRun->metadata['last_sync_action'])->toBe('labeled');

    // Should not create a new run or dispatch review job
    expect(Run::query()->count())->toBe(1);
    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('syncs metadata when reviewers are requested on an existing run', function (): void {
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

    // Create an existing run
    $existingRun = Run::factory()->forRepository($repository)->create([
        'external_reference' => 'github:pull_request:42:abc123',
        'metadata' => [
            'pull_request_number' => 42,
            'reviewers' => [],
        ],
    ]);

    Queue::fake();

    $payload = [
        'action' => 'review_requested',
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
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [
                ['login' => 'reviewer1', 'avatar_url' => 'https://example.com/avatar.png'],
            ],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Create a fake greeting action that should NOT be called for metadata sync
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called for metadata sync');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $existingRun->refresh();

    expect($existingRun->metadata['reviewers'])->toHaveCount(1)
        ->and($existingRun->metadata['reviewers'][0]['login'])->toBe('reviewer1')
        ->and($existingRun->metadata['last_sync_action'])->toBe('review_requested');

    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('does not sync metadata when no existing run is found', function (): void {
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

    Queue::fake();

    $payload = [
        'action' => 'labeled',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 999, // Non-existent PR
            'title' => 'Test PR',
            'body' => 'Test body',
            'draft' => false,
            'user' => ['login' => 'testuser', 'avatar_url' => null],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [
                ['name' => 'bug', 'color' => 'd73a4a'],
            ],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Create a fake greeting action that should NOT be called for metadata sync
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called for metadata sync');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    // No runs should exist and no review should be triggered
    expect(Run::query()->count())->toBe(0);
    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('creates skipped run and posts error comment when repository has config error', function (): void {
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

    // Create settings with a config error
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create([
        'config_error' => 'Invalid YAML: unexpected end of stream',
    ]);

    Queue::fake();

    $payload = [
        'action' => 'opened',
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
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Create a fake greeting action that should NOT be called
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called when config has error');
        }
    };

    // Create a fake config error poster that tracks calls
    $fakeConfigErrorPoster = new class implements PostsConfigErrorComment
    {
        public bool $wasCalled = false;

        public string $capturedError = '';

        public function handle(Repository $repository, int $pullRequestNumber, string $error): ?int
        {
            $this->wasCalled = true;
            $this->capturedError = $error;

            return 99999;
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        $fakeConfigErrorPoster,
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $run = Run::query()->first();

    expect($fakeConfigErrorPoster->wasCalled)->toBeTrue()
        ->and($fakeConfigErrorPoster->capturedError)->toBe('Invalid YAML: unexpected end of stream')
        ->and($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Skipped)
        ->and($run?->metadata['skip_reason'])->toContain('Configuration error')
        ->and($run?->metadata['skip_reason'])->toContain('Invalid YAML')
        ->and($run?->completed_at)->not->toBeNull();

    // Should not dispatch review job for skipped runs
    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('creates normal run when repository has no config error', function (): void {
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

    // Create settings without a config error
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create([
        'config_error' => null,
        'sentinel_config' => [
            'version' => 1,
            'review' => ['auto_review' => true],
        ],
    ]);

    Queue::fake();

    $payload = [
        'action' => 'opened',
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
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    $fakeGreeting = new class implements PostsGreetingComment
    {
        public bool $wasCalled = false;

        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            $this->wasCalled = true;

            return 12345;
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $run = Run::query()->first();

    expect($fakeGreeting->wasCalled)->toBeTrue()
        ->and($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Queued)
        ->and($run?->metadata)->not->toHaveKey('skip_reason');

    Queue::assertPushed(ExecuteReviewRun::class);
});

it('creates skipped run when PR target branch does not match trigger rules', function (): void {
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

    // Create settings with trigger rules that only allow PRs to 'main'
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create([
        'sentinel_config' => [
            'version' => 1,
            'triggers' => [
                'target_branches' => ['main'],
            ],
        ],
    ]);

    Queue::fake();

    $payload = [
        'action' => 'opened',
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
            'base' => ['ref' => 'develop'], // Not 'main', should be skipped
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    // Greeting should NOT be called for skipped trigger rules
    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called for trigger rule skip');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $run = Run::query()->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Skipped)
        ->and($run?->metadata['skip_reason'])->toContain('develop')
        ->and($run?->metadata['skip_reason'])->toContain('does not match');

    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('creates skipped run when PR author is in skip list', function (): void {
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

    // Create settings with skip authors configured
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create([
        'sentinel_config' => [
            'version' => 1,
            'triggers' => [
                'target_branches' => ['main'],
                'skip_authors' => ['dependabot[bot]', 'renovate[bot]'],
            ],
        ],
    ]);

    Queue::fake();

    $payload = [
        'action' => 'opened',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Bump dependency',
            'body' => 'Test body',
            'draft' => false,
            'user' => ['login' => 'dependabot[bot]', 'avatar_url' => null], // Skip author
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'dependabot/npm-update', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'dependabot[bot]'],
    ];

    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called for skip author');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $run = Run::query()->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Skipped)
        ->and($run?->metadata['skip_reason'])->toContain('dependabot[bot]')
        ->and($run?->metadata['skip_reason'])->toContain('skip list');

    Queue::assertNotPushed(ExecuteReviewRun::class);
});

it('creates skipped run when PR has skip label', function (): void {
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

    // Create settings with skip labels configured
    RepositorySettings::factory()->forRepository($repository)->autoReviewEnabled()->create([
        'sentinel_config' => [
            'version' => 1,
            'triggers' => [
                'target_branches' => ['main'],
                'skip_labels' => ['no-review', 'wip'],
            ],
        ],
    ]);

    Queue::fake();

    $payload = [
        'action' => 'opened',
        'installation' => ['id' => 12345678],
        'repository' => [
            'id' => 987654,
            'full_name' => 'org/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Work in progress',
            'body' => 'Test body',
            'draft' => false,
            'user' => ['login' => 'testuser', 'avatar_url' => null],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'abc123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [
                ['name' => 'no-review', 'color' => 'ff0000'],
            ],
        ],
        'sender' => ['login' => 'testuser'],
    ];

    $fakeGreeting = new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            throw new Exception('Greeting action should not be called for skip label');
        }
    };

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(
        app(GitHubWebhookService::class),
        app(CreatePullRequestRun::class),
        app(SyncPullRequestRunMetadata::class),
        $fakeGreeting,
        fakeConfigErrorPoster(),
        app(TriggerRuleEvaluator::class),
        app(QueueResolver::class)
    );

    $run = Run::query()->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe(RunStatus::Skipped)
        ->and($run?->metadata['skip_reason'])->toContain('no-review');

    Queue::assertNotPushed(ExecuteReviewRun::class);
});
