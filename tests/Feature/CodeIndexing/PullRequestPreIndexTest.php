<?php

declare(strict_types=1);

use App\Actions\CodeIndexing\DispatchPullRequestPreIndex;
use App\Actions\CodeIndexing\HandlePullRequestPreIndex;
use App\Enums\Auth\ProviderType;
use App\Enums\CodeIndexing\CodeIndexScopeType;
use App\Jobs\CodeIndexing\IndexCodeBatchJob;
use App\Jobs\CodeIndexing\ProcessPullRequestPreIndex;
use App\Models\CodeIndex;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use App\Services\Reviews\ValueObjects\GitHubUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'reviews.pr_preindex.enabled' => true,
        'reviews.pr_preindex.mode' => 'async',
        'reviews.pr_preindex.eligibility.tiers' => ['foundation', 'illuminate', 'orchestrate', 'sanctum'],
        'reviews.pr_preindex.eligibility.max_files_changed' => 120,
        'reviews.pr_preindex.eligibility.max_lines_changed' => 8000,
        'reviews.pr_preindex.indexing.max_files' => 40,
        'reviews.pr_preindex.indexing.max_file_size' => 120000,
        'reviews.pr_preindex.indexing.batch_size' => 25,
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

    $this->payload = new PullRequestWebhookPayload(
        action: 'opened',
        installationId: 12345,
        repositoryId: (int) $this->repository->github_id,
        repositoryFullName: 'acme/repo',
        pullRequestNumber: 42,
        pullRequestTitle: 'Improve pipeline',
        pullRequestBody: null,
        baseBranch: 'main',
        headBranch: 'feature/preindex',
        headSha: 'headsha123',
        senderLogin: 'octocat',
        author: new GitHubUser(login: 'octocat'),
        isDraft: false,
        assignees: [],
        reviewers: [],
        labels: [],
    );
});

it('queues pull request pre-index job in async mode', function (): void {
    Queue::fake();

    $action = app(DispatchPullRequestPreIndex::class);
    $action->handle($this->repository, $this->payload);

    Queue::assertPushed(ProcessPullRequestPreIndex::class, function (ProcessPullRequestPreIndex $job): bool {
        return $job->repository->id === $this->repository->id
            && $job->payload->pullRequestNumber === 42
            && $job->payload->headSha === 'headsha123';
    });
});

it('runs pull request pre-index inline in blocking mode', function (): void {
    config(['reviews.pr_preindex.mode' => 'blocking']);
    Queue::fake();

    $githubApi = mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('getPullRequestFiles')
        ->once()
        ->andReturn([]);

    $codeIndexingService = mock(CodeIndexingServiceContract::class);

    app()->instance(HandlePullRequestPreIndex::class, new HandlePullRequestPreIndex(
        gitHubApiService: $githubApi,
        codeIndexingService: $codeIndexingService,
        indexBatchDispatchStrategy: app(App\Services\CodeIndexing\Strategies\IndexBatchDispatchStrategy::class),
    ));

    $action = app(DispatchPullRequestPreIndex::class);
    $action->handle($this->repository, $this->payload);

    Queue::assertNotPushed(ProcessPullRequestPreIndex::class);
});

it('filters pre-index files and clears previous pull request scope rows', function (): void {
    Queue::fake();

    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'scope_type' => CodeIndexScopeType::PullRequest,
        'scope_ref' => 'pr:42@oldsha',
        'pull_request_number' => 42,
        'head_sha' => 'oldsha',
        'file_path' => 'app/Models/Legacy.php',
        'commit_sha' => 'oldsha',
    ]);

    $githubApi = mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('getPullRequestFiles')
        ->once()
        ->andReturn([
            [
                'filename' => 'app/Models/User.php',
                'status' => 'modified',
                'additions' => 10,
                'deletions' => 3,
                'changes' => 13,
                'size' => 400,
            ],
            [
                'filename' => 'public/logo.png',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 1,
                'changes' => 2,
                'size' => 200,
            ],
            [
                'filename' => 'app/TooLarge.php',
                'status' => 'modified',
                'additions' => 2,
                'deletions' => 2,
                'changes' => 4,
                'size' => 200000,
            ],
            [
                'filename' => 'app/Removed.php',
                'status' => 'removed',
                'additions' => 0,
                'deletions' => 12,
                'changes' => 12,
                'size' => 300,
            ],
        ]);

    $codeIndexingService = mock(CodeIndexingServiceContract::class);
    $codeIndexingService->shouldReceive('shouldIndexFile')
        ->with('app/Models/User.php')
        ->once()
        ->andReturnTrue();
    $codeIndexingService->shouldReceive('shouldIndexFile')
        ->with('public/logo.png')
        ->once()
        ->andReturnFalse();

    app()->instance(GitHubApiServiceContract::class, $githubApi);
    app()->instance(CodeIndexingServiceContract::class, $codeIndexingService);

    $action = app(HandlePullRequestPreIndex::class);
    $action->handle($this->repository, $this->payload);

    Queue::assertPushed(IndexCodeBatchJob::class, function (IndexCodeBatchJob $job): bool {
        return $job->repository->id === $this->repository->id
            && $job->commitSha === 'headsha123'
            && count($job->files) === 1
            && $job->files[0]['path'] === 'app/Models/User.php'
            && $job->scope['scope_type'] === CodeIndexScopeType::PullRequest->value
            && $job->scope['scope_ref'] === 'pr:42@headsha123'
            && $job->scope['pull_request_number'] === 42
            && $job->scope['head_sha'] === 'headsha123';
    });

    expect(CodeIndex::query()
        ->forRepository($this->repository)
        ->forPullRequest(42)
        ->count())->toBe(0);
});
