<?php

declare(strict_types=1);

use App\Actions\GitHub\PostConfigErrorComment;
use App\Contracts\GitHub\GitHubApiServiceContract;
use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Services\SentinelMessageService;

beforeEach(function (): void {
    $this->provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('posts config error comment to pull request', function (): void {
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/repo',
    ]);

    $mockGitHubApi = mock(GitHubApiServiceContract::class);
    $mockGitHubApi->expects('createPullRequestComment')
        ->withArgs(function ($installationId, $owner, $repo, $number, $body) {
            return $installationId === 12345678
                && $owner === 'org'
                && $repo === 'repo'
                && $number === 42
                && str_contains($body, 'Sentinel Configuration Error')
                && str_contains($body, 'Invalid YAML syntax');
        })
        ->andReturn(['id' => 99999]);

    $action = new PostConfigErrorComment(
        $mockGitHubApi,
        app(SentinelMessageService::class)
    );

    $commentId = $action->handle($repository, 42, 'Invalid YAML syntax');

    expect($commentId)->toBe(99999);
});

it('returns null when repository installation relationship returns null', function (): void {
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/repo',
    ]);

    // Delete the installation to simulate a missing installation
    $installation->delete();
    $repository->unsetRelation('installation');

    $mockGitHubApi = mock(GitHubApiServiceContract::class);
    $mockGitHubApi->shouldNotReceive('createPullRequestComment');

    $action = new PostConfigErrorComment(
        $mockGitHubApi,
        app(SentinelMessageService::class)
    );

    $commentId = $action->handle($repository, 42, 'Invalid YAML syntax');

    expect($commentId)->toBeNull();
});

it('returns null when github api fails', function (): void {
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345678,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/repo',
    ]);

    $mockGitHubApi = mock(GitHubApiServiceContract::class);
    $mockGitHubApi->expects('createPullRequestComment')
        ->andThrow(new RuntimeException('GitHub API error'));

    $action = new PostConfigErrorComment(
        $mockGitHubApi,
        app(SentinelMessageService::class)
    );

    $commentId = $action->handle($repository, 42, 'Invalid YAML syntax');

    expect($commentId)->toBeNull();
});
