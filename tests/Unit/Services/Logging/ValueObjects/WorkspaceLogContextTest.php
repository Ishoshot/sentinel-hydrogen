<?php

declare(strict_types=1);

use App\Models\Workspace;
use App\Services\Logging\ValueObjects\WorkspaceLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can be constructed with all parameters', function (): void {
    $context = new WorkspaceLogContext(
        workspaceId: 1,
        workspaceName: 'Test Workspace',
    );

    expect($context->workspaceId)->toBe(1);
    expect($context->workspaceName)->toBe('Test Workspace');
});

it('can be constructed with optional name null', function (): void {
    $context = new WorkspaceLogContext(
        workspaceId: 1,
    );

    expect($context->workspaceName)->toBeNull();
});

it('can be created from workspace model', function (): void {
    $workspace = Workspace::factory()->create([
        'name' => 'My Workspace',
    ]);

    $context = WorkspaceLogContext::fromWorkspace($workspace);

    expect($context->workspaceId)->toBe($workspace->id);
    expect($context->workspaceName)->toBe('My Workspace');
});

it('converts to array correctly', function (): void {
    $context = new WorkspaceLogContext(
        workspaceId: 1,
        workspaceName: 'Test Workspace',
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'workspace_id' => 1,
        'workspace_name' => 'Test Workspace',
    ]);
});

it('converts to array with null name', function (): void {
    $context = new WorkspaceLogContext(
        workspaceId: 1,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'workspace_id' => 1,
        'workspace_name' => null,
    ]);
});
