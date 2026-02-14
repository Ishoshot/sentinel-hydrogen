<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\LinkedIssueCollector;
use App\Services\Context\ContextBag;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

it('extracts issue numbers from PR body with Fixes keyword', function (): void {
    $collector = app(LinkedIssueCollector::class);

    $reflection = new ReflectionClass($collector);
    $method = $reflection->getMethod('extractIssueNumbers');
    $method->setAccessible(true);

    $body = 'This PR fixes #123 and also Fixes #456';
    $numbers = $method->invoke($collector, $body);

    expect($numbers)->toContain(123, 456);
});

it('extracts issue numbers from PR body with Closes keyword', function (): void {
    $collector = app(LinkedIssueCollector::class);

    $reflection = new ReflectionClass($collector);
    $method = $reflection->getMethod('extractIssueNumbers');
    $method->setAccessible(true);

    $body = 'Closes #789 and closed #101';
    $numbers = $method->invoke($collector, $body);

    expect($numbers)->toContain(789, 101);
});

it('extracts issue numbers from PR body with Resolves keyword', function (): void {
    $collector = app(LinkedIssueCollector::class);

    $reflection = new ReflectionClass($collector);
    $method = $reflection->getMethod('extractIssueNumbers');
    $method->setAccessible(true);

    $body = 'This resolves #200 and Resolved #201';
    $numbers = $method->invoke($collector, $body);

    expect($numbers)->toContain(200, 201);
});

it('extracts plain issue references', function (): void {
    $collector = app(LinkedIssueCollector::class);

    $reflection = new ReflectionClass($collector);
    $method = $reflection->getMethod('extractIssueNumbers');
    $method->setAccessible(true);

    $body = 'Related to #50 and see #51 for context';
    $numbers = $method->invoke($collector, $body);

    expect($numbers)->toContain(50, 51);
});

it('deduplicates issue numbers', function (): void {
    $collector = app(LinkedIssueCollector::class);

    $reflection = new ReflectionClass($collector);
    $method = $reflection->getMethod('extractIssueNumbers');
    $method->setAccessible(true);

    $body = 'Fixes #123 and also see #123 again, closes #123';
    $numbers = $method->invoke($collector, $body);

    expect($numbers)->toHaveCount(1)
        ->and($numbers)->toContain(123);
});

it('should not collect when PR body is empty', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_body' => '',
        ],
    ]);

    $collector = app(LinkedIssueCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeFalse();
});

it('should collect when PR body contains issue references', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_body' => 'Fixes #123',
        ],
    ]);

    $collector = app(LinkedIssueCollector::class);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($shouldCollect)->toBeTrue();
});

it('has correct priority', function (): void {
    $collector = app(LinkedIssueCollector::class);

    expect($collector->priority())->toBe(80);
});

it('has correct name', function (): void {
    $collector = app(LinkedIssueCollector::class);

    expect($collector->name())->toBe('linked_issues');
});

it('collects linked issues with normalized labels and comments', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 9911,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'acme/platform',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_body' => 'Fixes #123',
        ],
    ]);

    $issueComments = [];
    foreach (range(1, 12) as $commentNumber) {
        $issueComments[] = [
            'user' => ['login' => 'user-'.$commentNumber],
            'body' => 'comment-'.$commentNumber,
        ];
    }

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getIssue')
        ->once()
        ->with(9911, 'acme', 'platform', 123)
        ->andReturn([
            'title' => 'Issue title',
            'body' => 'Issue body',
            'state' => 'open',
            'labels' => [
                ['name' => 'bug'],
                ['name' => 'high-priority'],
                ['id' => 77],
            ],
        ]);
    $gitHubService->shouldReceive('getIssueComments')
        ->once()
        ->with(9911, 'acme', 'platform', 123)
        ->andReturn($issueComments);

    $collector = new LinkedIssueCollector($gitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->linkedIssues)->toHaveCount(1)
        ->and($bag->linkedIssues[0]['number'])->toBe(123)
        ->and($bag->linkedIssues[0]['title'])->toBe('Issue title')
        ->and($bag->linkedIssues[0]['labels'])->toBe(['bug', 'high-priority'])
        ->and($bag->linkedIssues[0]['comments'])->toHaveCount(10)
        ->and($bag->linkedIssues[0]['comments'][0])->toBe([
            'author' => 'user-1',
            'body' => 'comment-1',
        ]);
});

it('skips linked issue records that are pull requests', function (): void {
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 6612,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'full_name' => 'acme/platform',
    ]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_body' => 'Fixes #44',
        ],
    ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getIssue')
        ->once()
        ->with(6612, 'acme', 'platform', 44)
        ->andReturn([
            'title' => 'PR masquerading as issue',
            'pull_request' => ['url' => 'https://api.github.com/repos/acme/platform/pulls/44'],
        ]);
    $gitHubService->shouldNotReceive('getIssueComments');

    $collector = new LinkedIssueCollector($gitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $repository,
        'run' => $run,
    ]);

    expect($bag->linkedIssues)->toBeEmpty();
});
