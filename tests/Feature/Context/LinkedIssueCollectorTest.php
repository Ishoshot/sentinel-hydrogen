<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\LinkedIssueCollector;

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
