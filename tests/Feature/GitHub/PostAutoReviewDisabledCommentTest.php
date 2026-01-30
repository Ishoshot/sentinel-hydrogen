<?php

declare(strict_types=1);

use App\Actions\GitHub\PostAutoReviewDisabledComment;
use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\SentinelMessageService;

beforeEach(function (): void {
    $this->provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('posts auto-review disabled comment to pull request', function (): void {
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
                && str_contains($body, 'Review skipped')
                && str_contains($body, 'Auto reviews are disabled');
        })
        ->andReturn(['id' => 99999]);

    $action = new PostAutoReviewDisabledComment(
        $mockGitHubApi,
        app(SentinelMessageService::class)
    );

    $commentId = $action->handle($repository, 42);

    expect($commentId)->toBe(99999);
});

it('returns null when repository has no installation', function (): void {
    $connection = Connection::factory()->forProvider($this->provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'org/repo',
    ]);

    $repository->setRelation('installation', null);

    $mockGitHubApi = mock(GitHubApiServiceContract::class);
    $mockGitHubApi->shouldNotReceive('createPullRequestComment');

    $action = new PostAutoReviewDisabledComment(
        $mockGitHubApi,
        app(SentinelMessageService::class)
    );

    $commentId = $action->handle($repository, 42);

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

    $action = new PostAutoReviewDisabledComment(
        $mockGitHubApi,
        app(SentinelMessageService::class)
    );

    $commentId = $action->handle($repository, 42);

    expect($commentId)->toBeNull();
});
