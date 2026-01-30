<?php

declare(strict_types=1);

use App\Actions\GitHub\CreateConfigPullRequest;
use App\Enums\Auth\ProviderType;
use App\Events\GitHub\ConfigPullRequestCreated;
use App\Jobs\GitHub\CreateConfigPullRequestJob;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    // Ensure GitHub provider exists
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

describe('CreateConfigPullRequest action', function (): void {
    it('prepares branch and returns compare URL when config does not exist', function (): void {
        Event::fake([ConfigPullRequestCreated::class]);

        $workspace = Workspace::factory()->create();
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config doesn't exist
        $mockService->shouldReceive('fileExists')
            ->with(12345, 'test-owner', 'test-repo', '.sentinel/config.yaml', 'main')
            ->once()
            ->andReturn(false);

        // Branch doesn't exist (throws exception)
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/sentinel/add-config')
            ->once()
            ->andThrow(new RuntimeException('Not Found', 404));

        // Get default branch SHA
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/main')
            ->once()
            ->andReturn(['object' => ['sha' => 'abc123']]);

        // Create branch
        $mockService->shouldReceive('createReference')
            ->with(12345, 'test-owner', 'test-repo', 'refs/heads/sentinel/add-config', 'abc123')
            ->once()
            ->andReturn(['ref' => 'refs/heads/sentinel/add-config']);

        // Create file
        $mockService->shouldReceive('createFile')
            ->withArgs(function ($installationId, $owner, $repo, $path, $content, $message, $branch) {
                return $installationId === 12345
                    && $owner === 'test-owner'
                    && $repo === 'test-repo'
                    && $path === '.sentinel/config.yaml'
                    && str_contains($content, 'version: 1')
                    && $branch === 'sentinel/add-config';
            })
            ->once()
            ->andReturn(['commit' => ['sha' => 'def456']]);

        $action = new CreateConfigPullRequest($mockService);
        $result = $action->handle($repository);

        $expectedCompareUrl = 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1';

        expect($result->isReady())->toBeTrue()
            ->and($result->compareUrl)->toBe($expectedCompareUrl);

        Event::assertDispatched(ConfigPullRequestCreated::class, function ($event) use ($workspace, $repository, $expectedCompareUrl) {
            return $event->workspaceId === $workspace->id
                && $event->repositoryId === $repository->id
                && $event->repositoryName === 'test-owner/test-repo'
                && $event->prUrl === $expectedCompareUrl;
        });
    });

    it('skips when config file already exists', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config already exists
        $mockService->shouldReceive('fileExists')
            ->with(12345, 'test-owner', 'test-repo', '.sentinel/config.yaml', 'main')
            ->once()
            ->andReturn(true);

        $action = new CreateConfigPullRequest($mockService);
        $result = $action->handle($repository);

        expect($result->wasSkipped())->toBeTrue()
            ->and($result->skippedReason)->toBe('Configuration file already exists');
    });

    it('returns compare URL when branch already exists', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config doesn't exist
        $mockService->shouldReceive('fileExists')
            ->with(12345, 'test-owner', 'test-repo', '.sentinel/config.yaml', 'main')
            ->once()
            ->andReturn(false);

        // Branch already exists
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/sentinel/add-config')
            ->once()
            ->andReturn(['ref' => 'refs/heads/sentinel/add-config']);

        $action = new CreateConfigPullRequest($mockService);
        $result = $action->handle($repository);

        $expectedCompareUrl = 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1';

        expect($result->isReady())->toBeTrue()
            ->and($result->compareUrl)->toBe($expectedCompareUrl);
    });

    it('returns error when permissions are insufficient', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config doesn't exist
        $mockService->shouldReceive('fileExists')
            ->with(12345, 'test-owner', 'test-repo', '.sentinel/config.yaml', 'main')
            ->once()
            ->andReturn(false);

        // Branch doesn't exist
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/sentinel/add-config')
            ->once()
            ->andThrow(new RuntimeException('Not Found', 404));

        // Get default branch - permission denied
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/main')
            ->once()
            ->andThrow(new RuntimeException('403 Forbidden - No permission', 403));

        $action = new CreateConfigPullRequest($mockService);
        $result = $action->handle($repository);

        expect($result->hasFailed())->toBeTrue()
            ->and($result->error)->toBe('Insufficient permissions. Please check your GitHub App permissions.');
    });

    it('returns error for resource not accessible by integration', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config doesn't exist
        $mockService->shouldReceive('fileExists')
            ->with(12345, 'test-owner', 'test-repo', '.sentinel/config.yaml', 'main')
            ->once()
            ->andReturn(false);

        // Branch doesn't exist
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/sentinel/add-config')
            ->once()
            ->andThrow(new RuntimeException('Not Found', 404));

        // Get default branch - resource not accessible
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/main')
            ->once()
            ->andThrow(new RuntimeException('Resource not accessible by integration', 403));

        $action = new CreateConfigPullRequest($mockService);
        $result = $action->handle($repository);

        expect($result->hasFailed())->toBeTrue()
            ->and($result->error)->toBe('Insufficient permissions. Please check your GitHub App permissions.');
    });

    it('fails when repository has no installation loaded', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create();
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
        ]);

        // Manually set installation relationship to null to simulate missing installation
        $repository->setRelation('installation', null);

        $mockService = mock(GitHubApiServiceContract::class);

        $action = new CreateConfigPullRequest($mockService);
        $result = $action->handle($repository);

        expect($result->hasFailed())->toBeTrue()
            ->and($result->error)->toBe('Repository has no associated installation');
    });
});

describe('CreateConfigPullRequestJob', function (): void {
    it('can be dispatched', function (): void {
        Queue::fake();

        $repository = Repository::factory()->create();

        CreateConfigPullRequestJob::dispatch($repository->id);

        Queue::assertPushed(CreateConfigPullRequestJob::class, function ($job) use ($repository) {
            return $job->repositoryId === $repository->id;
        });
    });

    it('handles missing repository gracefully', function (): void {
        // Test that the job doesn't throw when repository is not found
        // The action won't be called because the repository doesn't exist
        $mockService = mock(GitHubApiServiceContract::class);
        $mockService->shouldNotReceive('fileExists');
        $mockService->shouldNotReceive('getReference');
        $mockService->shouldNotReceive('createReference');
        $mockService->shouldNotReceive('createFile');

        $action = new CreateConfigPullRequest($mockService);
        $job = new CreateConfigPullRequestJob(99999);
        $job->handle($action);

        // If we get here without exception, the test passes
        expect(true)->toBeTrue();
    });
});

describe('CreateConfigPr API endpoint', function (): void {
    it('returns compare URL via API as owner', function (): void {
        Event::fake([ConfigPullRequestCreated::class]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->teamMembers()->create([
            'user_id' => $user->id,
            'team_id' => $workspace->team->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config doesn't exist
        $mockService->shouldReceive('fileExists')
            ->andReturn(false);

        // Branch already exists - returns compare URL
        $mockService->shouldReceive('getReference')
            ->with(12345, 'test-owner', 'test-repo', 'heads/sentinel/add-config')
            ->andReturn(['ref' => 'refs/heads/sentinel/add-config']);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('repositories.create-config-pr', [$workspace, $repository]))
            ->assertOk()
            ->assertJson([
                'status' => 'ready',
                'compare_url' => 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1',
            ]);
    });

    it('returns skipped when config already exists', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->teamMembers()->create([
            'user_id' => $user->id,
            'team_id' => $workspace->team->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $provider = Provider::where('type', ProviderType::GitHub)->first();
        $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
        $installation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 12345,
        ]);
        $repository = Repository::factory()->create([
            'workspace_id' => $workspace->id,
            'installation_id' => $installation->id,
            'full_name' => 'test-owner/test-repo',
            'name' => 'test-repo',
            'default_branch' => 'main',
        ]);
        RepositorySettings::factory()->create(['repository_id' => $repository->id, 'workspace_id' => $workspace->id]);

        $mockService = mock(GitHubApiServiceContract::class);

        // Config already exists
        $mockService->shouldReceive('fileExists')
            ->andReturn(true);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('repositories.create-config-pr', [$workspace, $repository]))
            ->assertOk()
            ->assertJson([
                'status' => 'skipped',
                'message' => 'Configuration file already exists',
            ]);
    });

    it('requires authentication', function (): void {
        $workspace = Workspace::factory()->create();
        $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

        $this->postJson(route('repositories.create-config-pr', [$workspace, $repository]))
            ->assertUnauthorized();
    });

    it('returns 404 for repository not in workspace', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->teamMembers()->create([
            'user_id' => $user->id,
            'team_id' => $workspace->team->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $otherWorkspace = Workspace::factory()->create();
        $repository = Repository::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('repositories.create-config-pr', [$workspace, $repository]))
            ->assertNotFound();
    });

    it('requires update permission on repository', function (): void {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->teamMembers()->create([
            'user_id' => $owner->id,
            'team_id' => $workspace->team->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        $workspace->teamMembers()->create([
            'user_id' => $member->id,
            'team_id' => $workspace->team->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson(route('repositories.create-config-pr', [$workspace, $repository]))
            ->assertForbidden();
    });
});

describe('ConfigPullRequestCreated event', function (): void {
    it('broadcasts to the correct channel', function (): void {
        $event = new ConfigPullRequestCreated(
            workspaceId: 123,
            repositoryId: 456,
            repositoryName: 'test-owner/test-repo',
            prUrl: 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1'
        );

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-workspace.123.repositories');
    });

    it('broadcasts with correct event name', function (): void {
        $event = new ConfigPullRequestCreated(
            workspaceId: 123,
            repositoryId: 456,
            repositoryName: 'test-owner/test-repo',
            prUrl: 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1'
        );

        expect($event->broadcastAs())->toBe('config-pr.created');
    });

    it('broadcasts with correct data', function (): void {
        $event = new ConfigPullRequestCreated(
            workspaceId: 123,
            repositoryId: 456,
            repositoryName: 'test-owner/test-repo',
            prUrl: 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1'
        );

        $data = $event->broadcastWith();

        expect($data)->toMatchArray([
            'repository_id' => 456,
            'repository_name' => 'test-owner/test-repo',
            'pr_url' => 'https://github.com/test-owner/test-repo/compare/main...sentinel/add-config?expand=1',
        ]);
    });
});
