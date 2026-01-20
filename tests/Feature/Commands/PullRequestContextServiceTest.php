<?php

declare(strict_types=1);

use App\Enums\CommandRunStatus;
use App\Enums\CommandType;
use App\Enums\ProviderType;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Commands\PullRequestContextService;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($this->workspace)->forProvider($provider)->create();
    $this->installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $this->repository = Repository::factory()->forInstallation($this->installation)->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $this->githubApi = Mockery::mock(GitHubApiServiceContract::class);
    app()->instance(GitHubApiServiceContract::class, $this->githubApi);
});

describe('buildContext', function (): void {
    it('returns null for non-PR commands', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Explain,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => false,
            'issue_number' => null,
        ]);

        $service = app(PullRequestContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeNull();
    });

    it('builds context for PR commands', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Review,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => true,
            'issue_number' => 42,
        ]);

        $this->githubApi->shouldReceive('getPullRequest')
            ->once()
            ->with(12345, 'owner', 'repo', 42)
            ->andReturn([
                'title' => 'Add user authentication',
                'body' => 'This PR adds OAuth2 authentication support.',
                'base' => ['ref' => 'main'],
                'head' => ['ref' => 'feature/auth'],
                'additions' => 150,
                'deletions' => 20,
                'changed_files' => 5,
            ]);

        $this->githubApi->shouldReceive('getPullRequestFiles')
            ->once()
            ->with(12345, 'owner', 'repo', 42)
            ->andReturn([
                [
                    'filename' => 'app/Http/Controllers/AuthController.php',
                    'status' => 'added',
                    'additions' => 100,
                    'deletions' => 0,
                    'patch' => '+public function login() { }',
                ],
                [
                    'filename' => 'routes/api.php',
                    'status' => 'modified',
                    'additions' => 50,
                    'deletions' => 20,
                    'patch' => '+Route::post("/login", [AuthController::class, "login"]);',
                ],
            ]);

        $this->githubApi->shouldReceive('getPullRequestComments')
            ->once()
            ->with(12345, 'owner', 'repo', 42)
            ->andReturn([
                [
                    'user' => ['login' => 'reviewer1'],
                    'body' => 'Looks good, but can you add tests?',
                ],
            ]);

        $service = app(PullRequestContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeString()
            ->and($context)->toContain('## Pull Request Context')
            ->and($context)->toContain('Add user authentication')
            ->and($context)->toContain('OAuth2 authentication')
            ->and($context)->toContain('feature/auth')
            ->and($context)->toContain('main')
            ->and($context)->toContain('+150')
            ->and($context)->toContain('-20')
            ->and($context)->toContain('AuthController.php')
            ->and($context)->toContain('routes/api.php')
            ->and($context)->toContain('@reviewer1')
            ->and($context)->toContain('add tests');
    });

    it('handles missing PR data gracefully', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Summarize,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => true,
            'issue_number' => 99,
        ]);

        $this->githubApi->shouldReceive('getPullRequest')
            ->once()
            ->andThrow(new RuntimeException('API rate limit exceeded'));

        $service = app(PullRequestContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeNull();
    });

    it('truncates long diffs', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Review,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => true,
            'issue_number' => 10,
        ]);

        // Create a very long patch
        $longPatch = str_repeat('+// Some code line here with content', 500);

        $this->githubApi->shouldReceive('getPullRequest')
            ->once()
            ->andReturn([
                'title' => 'Large change',
                'body' => 'Big diff PR',
                'base' => ['ref' => 'main'],
                'head' => ['ref' => 'feature/large'],
                'additions' => 5000,
                'deletions' => 100,
                'changed_files' => 1,
            ]);

        $this->githubApi->shouldReceive('getPullRequestFiles')
            ->once()
            ->andReturn([
                [
                    'filename' => 'app/Services/LargeService.php',
                    'status' => 'modified',
                    'additions' => 5000,
                    'deletions' => 100,
                    'patch' => $longPatch,
                ],
            ]);

        $this->githubApi->shouldReceive('getPullRequestComments')
            ->once()
            ->andReturn([]);

        $service = app(PullRequestContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeString()
            ->and($context)->toContain('(diff truncated)')
            ->and(mb_strlen($context))->toBeLessThan(10000);
    });
});

describe('getMetadata', function (): void {
    it('returns null for non-PR commands', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Find,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => false,
            'issue_number' => null,
        ]);

        $service = app(PullRequestContextService::class);
        $metadata = $service->getMetadata($commandRun);

        expect($metadata)->toBeNull();
    });

    it('returns PR metadata for PR commands', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Review,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => true,
            'issue_number' => 15,
        ]);

        $this->githubApi->shouldReceive('getPullRequest')
            ->once()
            ->with(12345, 'owner', 'repo', 15)
            ->andReturn([
                'title' => 'Fix authentication bug',
                'additions' => 25,
                'deletions' => 10,
                'changed_files' => 3,
            ]);

        $service = app(PullRequestContextService::class);
        $metadata = $service->getMetadata($commandRun);

        expect($metadata)->toBeArray()
            ->and($metadata['pr_title'])->toBe('Fix authentication bug')
            ->and($metadata['pr_additions'])->toBe(25)
            ->and($metadata['pr_deletions'])->toBe(10)
            ->and($metadata['pr_changed_files'])->toBe(3)
            ->and($metadata['pr_context_included'])->toBeTrue();
    });

    it('handles API errors gracefully', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Analyze,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => true,
            'issue_number' => 99,
        ]);

        $this->githubApi->shouldReceive('getPullRequest')
            ->once()
            ->andThrow(new RuntimeException('Network error'));

        $service = app(PullRequestContextService::class);
        $metadata = $service->getMetadata($commandRun);

        expect($metadata)->toBeNull();
    });
});
