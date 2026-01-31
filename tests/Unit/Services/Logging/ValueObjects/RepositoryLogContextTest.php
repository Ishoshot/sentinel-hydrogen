<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Services\Logging\ValueObjects\RepositoryLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can be constructed with all parameters', function (): void {
    $context = new RepositoryLogContext(
        repositoryId: 1,
        workspaceId: 10,
        installationId: 5,
        repositoryName: 'owner/repo',
    );

    expect($context->repositoryId)->toBe(1);
    expect($context->workspaceId)->toBe(10);
    expect($context->installationId)->toBe(5);
    expect($context->repositoryName)->toBe('owner/repo');
});

it('can be constructed with optional parameters null', function (): void {
    $context = new RepositoryLogContext(
        repositoryId: 1,
    );

    expect($context->workspaceId)->toBeNull();
    expect($context->installationId)->toBeNull();
    expect($context->repositoryName)->toBeNull();
});

it('can be created from repository model', function (): void {
    $repository = Repository::factory()->create([
        'full_name' => 'test-owner/test-repo',
    ]);

    $context = RepositoryLogContext::fromRepository($repository);

    expect($context->repositoryId)->toBe($repository->id);
    expect($context->workspaceId)->toBe($repository->workspace_id);
    expect($context->installationId)->toBe($repository->installation_id);
    expect($context->repositoryName)->toBe('test-owner/test-repo');
});

it('converts to array correctly', function (): void {
    $context = new RepositoryLogContext(
        repositoryId: 1,
        workspaceId: 10,
        installationId: 5,
        repositoryName: 'owner/repo',
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'repository_id' => 1,
        'workspace_id' => 10,
        'installation_id' => 5,
        'repository_name' => 'owner/repo',
    ]);
});

it('converts to array with null values', function (): void {
    $context = new RepositoryLogContext(
        repositoryId: 1,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'repository_id' => 1,
        'workspace_id' => null,
        'installation_id' => null,
        'repository_name' => null,
    ]);
});
