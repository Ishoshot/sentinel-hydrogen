<?php

declare(strict_types=1);

use App\Actions\GitHub\HandleInstallationWebhook;
use App\Enums\GitHub\InstallationStatus;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->connection = Connection::factory()->forWorkspace($this->workspace)->create();
});

it('handles created action and resolves installation', function (): void {
    Log::spy();

    $installation = Installation::factory()->forConnection($this->connection)->create([
        'installation_id' => 99001,
    ]);

    $payload = buildInstallationPayload('created', 99001);

    app(HandleInstallationWebhook::class)->handle($payload);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Processing installation webhook'));
});

it('logs warning when created webhook has no matching installation record', function (): void {
    Log::spy();

    $payload = buildInstallationPayload('created', 88888);

    app(HandleInstallationWebhook::class)->handle($payload);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'no installation record found'));
});

it('handles deleted action and marks installation as uninstalled', function (): void {
    Log::spy();

    $appService = Mockery::mock(GitHubAppServiceContract::class);
    $appService->shouldReceive('clearInstallationToken')->once()->with(99002);
    $appService->shouldReceive('generateJwt')->never();
    app()->instance(GitHubAppServiceContract::class, $appService);

    $installation = Installation::factory()->forConnection($this->connection)->create([
        'installation_id' => 99002,
        'status' => InstallationStatus::Active,
    ]);

    $payload = buildInstallationPayload('deleted', 99002);

    app(HandleInstallationWebhook::class)->handle($payload);

    $installation->refresh();
    expect($installation->status)->toBe(InstallationStatus::Uninstalled);

    $this->connection->refresh();
    expect($this->connection->status)->toBe(ConnectionStatus::Disconnected);
});

it('handles deleted action gracefully when installation does not exist', function (): void {
    Log::spy();

    $payload = buildInstallationPayload('deleted', 77777);

    app(HandleInstallationWebhook::class)->handle($payload);

    // Should not throw; just silently return
    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Processing installation webhook'));
});

it('handles suspend action and marks installation as suspended', function (): void {
    Log::spy();

    $installation = Installation::factory()->forConnection($this->connection)->create([
        'installation_id' => 99003,
        'status' => InstallationStatus::Active,
    ]);

    $payload = buildInstallationPayload('suspend', 99003);

    app(HandleInstallationWebhook::class)->handle($payload);

    $installation->refresh();
    expect($installation->status)->toBe(InstallationStatus::Suspended)
        ->and($installation->suspended_at)->not->toBeNull();
});

it('handles suspend action gracefully when installation does not exist', function (): void {
    Log::spy();

    $payload = buildInstallationPayload('suspend', 66666);

    app(HandleInstallationWebhook::class)->handle($payload);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Processing installation webhook'));
});

it('handles unsuspend action and marks installation as active', function (): void {
    Log::spy();

    $installation = Installation::factory()->forConnection($this->connection)->suspended()->create([
        'installation_id' => 99004,
    ]);

    $payload = buildInstallationPayload('unsuspend', 99004);

    app(HandleInstallationWebhook::class)->handle($payload);

    $installation->refresh();
    expect($installation->status)->toBe(InstallationStatus::Active)
        ->and($installation->suspended_at)->toBeNull();
});

it('handles unsuspend action gracefully when installation does not exist', function (): void {
    Log::spy();

    $payload = buildInstallationPayload('unsuspend', 55555);

    app(HandleInstallationWebhook::class)->handle($payload);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Processing installation webhook'));
});

it('ignores unknown actions', function (): void {
    Log::spy();

    $payload = buildInstallationPayload('new_permissions_accepted', 99005);

    app(HandleInstallationWebhook::class)->handle($payload);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Ignoring installation action'));
});

/**
 * Build a GitHub installation webhook payload.
 *
 * @return array<string, mixed>
 */
function buildInstallationPayload(string $action, int $installationId): array
{
    return [
        'action' => $action,
        'installation' => [
            'id' => $installationId,
            'account' => [
                'type' => 'Organization',
                'login' => 'test-org',
                'avatar_url' => 'https://avatars.test/test-org',
            ],
            'permissions' => [
                'contents' => 'read',
                'pull_requests' => 'write',
            ],
            'events' => ['pull_request'],
        ],
    ];
}
