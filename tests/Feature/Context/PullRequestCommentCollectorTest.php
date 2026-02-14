<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\PullRequestCommentCollector;
use App\Services\Context\ContextBag;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

it('has correct name and priority', function (): void {
    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new PullRequestCommentCollector($gitHubService);

    expect($collector->name())->toBe('pr_comments')
        ->and($collector->priority())->toBe(70);
});

it('checks required parameters in shouldCollect', function (): void {
    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new PullRequestCommentCollector($gitHubService);

    $repository = Repository::factory()->create();
    $run = Run::factory()->forRepository($repository)->create();

    expect($collector->shouldCollect([]))->toBeFalse()
        ->and($collector->shouldCollect(['repository' => $repository]))->toBeFalse()
        ->and($collector->shouldCollect(['run' => $run]))->toBeFalse()
        ->and($collector->shouldCollect(['repository' => $repository, 'run' => $run]))->toBeTrue();
});

it('collects and normalizes pull request comments', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 5588,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'acme/platform',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => ['pull_request_number' => 77],
    ]);

    $rawComments = [
        [
            'user' => ['login' => 'alice'],
            'body' => 'Please add tests.',
            'created_at' => '2026-01-01T00:00:00Z',
        ],
        [
            'user' => ['login' => 'dependabot[bot]'],
            'body' => 'Bump dependency.',
            'created_at' => '2026-01-01T00:05:00Z',
        ],
        [
            'user' => ['login' => 'reviewer'],
            'body' => '',
            'created_at' => '2026-01-01T00:10:00Z',
        ],
        [
            'user' => ['login' => 'sentinel'],
            'body' => '<!-- sentinel-review --> generated',
            'created_at' => '2026-01-01T00:15:00Z',
        ],
    ];

    foreach (range(1, 25) as $index) {
        $rawComments[] = [
            'user' => ['login' => 'human-'.$index],
            'body' => 'comment-'.$index,
            'created_at' => '2026-01-01T00:20:00Z',
        ];
    }

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getPullRequestComments')
        ->once()
        ->with(5588, 'acme', 'platform', 77)
        ->andReturn($rawComments);

    $collector = new PullRequestCommentCollector($gitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->prComments)->toHaveCount(20)
        ->and($bag->prComments[0])->toBe([
            'author' => 'alice',
            'body' => 'Please add tests.',
            'created_at' => '2026-01-01T00:00:00Z',
        ])
        ->and(collect($bag->prComments)->contains(fn (array $comment): bool => $comment['author'] === 'dependabot[bot]'))->toBeFalse()
        ->and(collect($bag->prComments)->contains(fn (array $comment): bool => str_contains($comment['body'], 'sentinel-review')))->toBeFalse();
});

it('does not call github when run has no pull request number', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'acme/platform',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [],
    ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldNotReceive('getPullRequestComments');

    $collector = new PullRequestCommentCollector($gitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->prComments)->toBeEmpty();
});
