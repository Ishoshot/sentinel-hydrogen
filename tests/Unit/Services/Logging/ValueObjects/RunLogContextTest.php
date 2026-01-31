<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\Run;
use App\Services\Logging\ValueObjects\RunLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can be constructed with all parameters', function (): void {
    $context = new RunLogContext(
        runId: 1,
        workspaceId: 10,
        repositoryId: 5,
    );

    expect($context->runId)->toBe(1);
    expect($context->workspaceId)->toBe(10);
    expect($context->repositoryId)->toBe(5);
});

it('can be constructed with optional parameters null', function (): void {
    $context = new RunLogContext(
        runId: 1,
    );

    expect($context->workspaceId)->toBeNull();
    expect($context->repositoryId)->toBeNull();
});

it('can be created from run model', function (): void {
    $repository = Repository::factory()->create();
    $run = Run::factory()->forRepository($repository)->create();

    $context = RunLogContext::fromRun($run);

    expect($context->runId)->toBe($run->id);
    expect($context->workspaceId)->toBe($run->workspace_id);
    expect($context->repositoryId)->toBe($run->repository_id);
});

it('converts to array correctly', function (): void {
    $context = new RunLogContext(
        runId: 1,
        workspaceId: 10,
        repositoryId: 5,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'run_id' => 1,
        'workspace_id' => 10,
        'repository_id' => 5,
    ]);
});

it('converts to array with null values', function (): void {
    $context = new RunLogContext(
        runId: 1,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'run_id' => 1,
        'workspace_id' => null,
        'repository_id' => null,
    ]);
});
