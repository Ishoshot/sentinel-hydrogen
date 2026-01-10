<?php

declare(strict_types=1);

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
use Exception;
use Illuminate\Support\Facades\Queue;

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
        $fakeGreeting
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
        $fakeGreeting
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
        $fakeGreeting
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
        $fakeGreeting
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
        $fakeGreeting
    );

    // No runs should exist and no review should be triggered
    expect(Run::query()->count())->toBe(0);
    Queue::assertNotPushed(ExecuteReviewRun::class);
});
