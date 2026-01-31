<?php

declare(strict_types=1);

use App\Services\Logging\ValueObjects\PullRequestLogContext;

it('can be constructed with all parameters', function (): void {
    $context = new PullRequestLogContext(
        repositoryId: 1,
        prNumber: 42,
        repositoryName: 'owner/repo',
        workspaceId: 10,
    );

    expect($context->repositoryId)->toBe(1);
    expect($context->prNumber)->toBe(42);
    expect($context->repositoryName)->toBe('owner/repo');
    expect($context->workspaceId)->toBe(10);
});

it('can be constructed with all parameters null', function (): void {
    $context = new PullRequestLogContext();

    expect($context->repositoryId)->toBeNull();
    expect($context->prNumber)->toBeNull();
    expect($context->repositoryName)->toBeNull();
    expect($context->workspaceId)->toBeNull();
});

it('can be created via static create method', function (): void {
    $context = PullRequestLogContext::create(
        repositoryId: 1,
        prNumber: 42,
        repositoryName: 'owner/repo',
        workspaceId: 10,
    );

    expect($context->repositoryId)->toBe(1);
    expect($context->prNumber)->toBe(42);
    expect($context->repositoryName)->toBe('owner/repo');
    expect($context->workspaceId)->toBe(10);
});

it('converts to array and filters null values', function (): void {
    $context = new PullRequestLogContext(
        repositoryId: 1,
        prNumber: 42,
        repositoryName: 'owner/repo',
        workspaceId: 10,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'repository_id' => 1,
        'workspace_id' => 10,
        'pr_number' => 42,
        'repository_name' => 'owner/repo',
    ]);
});

it('converts to array filtering out null values', function (): void {
    $context = new PullRequestLogContext(
        repositoryId: 1,
        prNumber: 42,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'repository_id' => 1,
        'pr_number' => 42,
    ]);
    expect($array)->not->toHaveKey('repository_name');
    expect($array)->not->toHaveKey('workspace_id');
});

it('returns empty array when all values are null', function (): void {
    $context = new PullRequestLogContext();

    $array = $context->toArray();

    expect($array)->toBe([]);
});
