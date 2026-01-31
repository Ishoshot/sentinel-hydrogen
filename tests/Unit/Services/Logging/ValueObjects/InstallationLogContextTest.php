<?php

declare(strict_types=1);

use App\Models\Installation;
use App\Services\Logging\ValueObjects\InstallationLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can be constructed with all parameters', function (): void {
    $context = new InstallationLogContext(
        installationId: 1,
        githubInstallationId: 12345,
        workspaceId: 10,
    );

    expect($context->installationId)->toBe(1);
    expect($context->githubInstallationId)->toBe(12345);
    expect($context->workspaceId)->toBe(10);
});

it('can be constructed with optional workspace id null', function (): void {
    $context = new InstallationLogContext(
        installationId: 1,
        githubInstallationId: 12345,
    );

    expect($context->workspaceId)->toBeNull();
});

it('can be created from installation model', function (): void {
    $installation = Installation::factory()->create([
        'installation_id' => 99999,
    ]);

    $context = InstallationLogContext::fromInstallation($installation);

    expect($context->installationId)->toBe($installation->id);
    expect($context->githubInstallationId)->toBe(99999);
    expect($context->workspaceId)->toBe($installation->workspace_id);
});

it('converts to array correctly', function (): void {
    $context = new InstallationLogContext(
        installationId: 1,
        githubInstallationId: 12345,
        workspaceId: 10,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'installation_id' => 1,
        'github_installation_id' => 12345,
        'workspace_id' => 10,
    ]);
});

it('converts to array with null workspace', function (): void {
    $context = new InstallationLogContext(
        installationId: 1,
        githubInstallationId: 12345,
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'installation_id' => 1,
        'github_installation_id' => 12345,
        'workspace_id' => null,
    ]);
});
