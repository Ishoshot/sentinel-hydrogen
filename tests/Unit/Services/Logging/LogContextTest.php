<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Logging\LogContext;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('builds context from Run model', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $context = LogContext::fromRun($run);

    expect($context)->toHaveKeys(['run_id', 'workspace_id', 'repository_id'])
        ->and($context['run_id'])->toBe($run->id)
        ->and($context['workspace_id'])->toBe($run->workspace_id)
        ->and($context['repository_id'])->toBe($run->repository_id);
});

it('builds context from Repository model', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $context = LogContext::fromRepository($repository);

    expect($context)->toHaveKeys(['repository_id', 'workspace_id', 'installation_id', 'repository_name'])
        ->and($context['repository_id'])->toBe($repository->id)
        ->and($context['workspace_id'])->toBe($repository->workspace_id)
        ->and($context['installation_id'])->toBe($repository->installation_id)
        ->and($context['repository_name'])->toBe($repository->full_name);
});

it('builds context from Workspace model', function (): void {
    $workspace = Workspace::factory()->create();

    $context = LogContext::fromWorkspace($workspace);

    expect($context)->toHaveKeys(['workspace_id', 'workspace_name'])
        ->and($context['workspace_id'])->toBe($workspace->id)
        ->and($context['workspace_name'])->toBe($workspace->name);
});

it('builds context from Installation model', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();

    $context = LogContext::fromInstallation($installation);

    expect($context)->toHaveKeys(['installation_id', 'github_installation_id', 'workspace_id'])
        ->and($context['installation_id'])->toBe($installation->id)
        ->and($context['github_installation_id'])->toBe($installation->installation_id)
        ->and($context['workspace_id'])->toBe($installation->workspace_id);
});

it('builds webhook context with all parameters', function (): void {
    $context = LogContext::forWebhook(
        installationId: 12345,
        repositoryName: 'owner/repo',
        action: 'opened'
    );

    expect($context)->toEqual([
        'github_installation_id' => 12345,
        'repository_name' => 'owner/repo',
        'action' => 'opened',
    ]);
});

it('builds webhook context filtering null values', function (): void {
    $context = LogContext::forWebhook(
        installationId: 12345,
        repositoryName: null,
        action: 'opened'
    );

    expect($context)->toEqual([
        'github_installation_id' => 12345,
        'action' => 'opened',
    ]);
});

it('builds pull request context with all parameters', function (): void {
    $context = LogContext::forPullRequest(
        repositoryId: 1,
        prNumber: 42,
        repositoryName: 'owner/repo',
        workspaceId: 5
    );

    expect($context)->toEqual([
        'repository_id' => 1,
        'workspace_id' => 5,
        'pr_number' => 42,
        'repository_name' => 'owner/repo',
    ]);
});

it('builds pull request context filtering null values', function (): void {
    $context = LogContext::forPullRequest(
        repositoryId: 1,
        prNumber: 42
    );

    expect($context)->toEqual([
        'repository_id' => 1,
        'pr_number' => 42,
    ]);
});

it('merges multiple context arrays', function (): void {
    $context1 = ['key1' => 'value1'];
    $context2 = ['key2' => 'value2'];
    $context3 = ['key3' => 'value3'];

    $merged = LogContext::merge($context1, $context2, $context3);

    expect($merged)->toEqual([
        'key1' => 'value1',
        'key2' => 'value2',
        'key3' => 'value3',
    ]);
});

it('adds exception context to array', function (): void {
    $exception = new RuntimeException('Test error message', 500);
    $baseContext = ['run_id' => 1];

    $context = LogContext::withException($baseContext, $exception);

    expect($context)->toHaveKeys(['run_id', 'exception_class', 'exception_message', 'exception_file', 'exception_line'])
        ->and($context['run_id'])->toBe(1)
        ->and($context['exception_class'])->toBe(RuntimeException::class)
        ->and($context['exception_message'])->toBe('Test error message');
});
