<?php

declare(strict_types=1);

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Enums\Auth\ProviderType;
use App\Jobs\CodeIndexing\ProcessPullRequestIndexCleanup;
use App\Jobs\CodeIndexing\ProcessPullRequestPreIndex;
use App\Jobs\GitHub\ProcessPullRequestWebhook;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'reviews.pr_preindex.enabled' => true,
        'reviews.pr_preindex.mode' => 'async',
        'reviews.pr_preindex.eligibility.tiers' => ['foundation', 'illuminate', 'orchestrate', 'sanctum'],
    ]);

    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true],
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);

    $this->repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'github_id' => 987654,
        'full_name' => 'acme/repo',
        'name' => 'repo',
    ]);

    app()->instance(FetchesSentinelConfig::class, new class implements FetchesSentinelConfig
    {
        public function handle(Repository $repository, ?string $ref = null): array
        {
            return [
                'found' => false,
                'content' => null,
                'sha' => null,
                'error' => null,
            ];
        }
    });

    app()->instance(PostsGreetingComment::class, new class implements PostsGreetingComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            return null;
        }
    });

    app()->instance(PostsConfigErrorComment::class, new class implements PostsConfigErrorComment
    {
        public function handle(Repository $repository, int $pullRequestNumber, string $error): ?int
        {
            return null;
        }
    });

    app()->instance(PostsAutoReviewDisabledComment::class, new class implements PostsAutoReviewDisabledComment
    {
        public function handle(Repository $repository, int $pullRequestNumber): ?int
        {
            return null;
        }
    });

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('getPullRequestFiles')
        ->andReturn([
            [
                'filename' => 'app/Services/Processor.php',
                'status' => 'modified',
                'additions' => 5,
                'deletions' => 1,
                'changes' => 6,
                'size' => 600,
            ],
        ]);

    app()->instance(GitHubApiServiceContract::class, $githubApi);
});

it('queues pull request index cleanup when pull request is closed', function (): void {
    Queue::fake();

    $payload = [
        'action' => 'closed',
        'installation' => ['id' => 12345],
        'repository' => [
            'id' => 987654,
            'full_name' => 'acme/repo',
        ],
        'pull_request' => [
            'number' => 42,
            'title' => 'Close PR',
            'body' => null,
            'draft' => false,
            'user' => ['login' => 'octocat', 'avatar_url' => null],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'close123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'octocat'],
    ];

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(app(App\Actions\Reviews\HandlePullRequestWebhook::class));

    Queue::assertPushed(ProcessPullRequestIndexCleanup::class, function (ProcessPullRequestIndexCleanup $job): bool {
        return $job->repository->id === $this->repository->id
            && $job->pullRequestNumber === 42;
    });
});

it('queues pull request pre-index when pull request is opened', function (): void {
    Queue::fake();

    $payload = [
        'action' => 'opened',
        'installation' => ['id' => 12345],
        'repository' => [
            'id' => 987654,
            'full_name' => 'acme/repo',
        ],
        'pull_request' => [
            'number' => 44,
            'title' => 'Open PR',
            'body' => 'Testing pre-index dispatch',
            'draft' => false,
            'user' => ['login' => 'octocat', 'avatar_url' => null],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature', 'sha' => 'open123'],
            'assignees' => [],
            'requested_reviewers' => [],
            'labels' => [],
        ],
        'sender' => ['login' => 'octocat'],
    ];

    $job = new ProcessPullRequestWebhook($payload);
    $job->handle(app(App\Actions\Reviews\HandlePullRequestWebhook::class));

    Queue::assertPushed(ProcessPullRequestPreIndex::class, function (ProcessPullRequestPreIndex $job): bool {
        return $job->repository->id === $this->repository->id
            && $job->payload['pull_request_number'] === 44
            && $job->payload['head_sha'] === 'open123';
    });
});
