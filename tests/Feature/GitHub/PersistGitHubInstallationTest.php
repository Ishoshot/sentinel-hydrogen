<?php

declare(strict_types=1);

use App\Actions\GitHub\PersistGitHubInstallation;
use App\Enums\Auth\ProviderType;
use App\Enums\GitHub\InstallationStatus;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->action = new PersistGitHubInstallation();

    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

describe('fromGitHub', function (): void {
    it('updates the connection to active with external id and metadata', function (): void {
        $connection = Connection::factory()->pending()->create();

        $installationId = 12345678;
        $installationData = [
            'account' => [
                'type' => 'User',
                'login' => 'testuser',
                'avatar_url' => 'https://example.com/avatar.png',
            ],
            'permissions' => ['contents' => 'read', 'pull_requests' => 'write'],
            'events' => ['pull_request', 'push'],
        ];

        $this->action->fromGitHub($connection, $installationId, $installationData);

        $connection->refresh();

        expect($connection->status)->toBe(ConnectionStatus::Active)
            ->and($connection->external_id)->toBe((string) $installationId)
            ->and($connection->metadata)->toHaveKey('connected_at');
    });

    it('merges metadata with existing metadata', function (): void {
        $connection = Connection::factory()->pending()->create([
            'metadata' => ['existing_key' => 'existing_value'],
        ]);

        $installationData = [
            'account' => ['type' => 'User', 'login' => 'testuser'],
            'permissions' => [],
            'events' => [],
        ];

        $this->action->fromGitHub($connection, 99999, $installationData);

        $connection->refresh();

        expect($connection->metadata)
            ->toHaveKey('existing_key')
            ->toHaveKey('connected_at');
    });

    it('creates a new installation record', function (): void {
        $connection = Connection::factory()->create();

        $installationId = 55555555;
        $installationData = [
            'account' => [
                'type' => 'Organization',
                'login' => 'test-org',
                'avatar_url' => 'https://example.com/org-avatar.png',
            ],
            'permissions' => ['contents' => 'read', 'metadata' => 'read'],
            'events' => ['push'],
        ];

        $installation = $this->action->fromGitHub($connection, $installationId, $installationData);

        expect($installation)->toBeInstanceOf(Installation::class)
            ->and($installation->installation_id)->toBe($installationId)
            ->and($installation->connection_id)->toBe($connection->id)
            ->and($installation->workspace_id)->toBe($connection->workspace_id)
            ->and($installation->account_type)->toBe('Organization')
            ->and($installation->account_login)->toBe('test-org')
            ->and($installation->account_avatar_url)->toBe('https://example.com/org-avatar.png')
            ->and($installation->status)->toBe(InstallationStatus::Active)
            ->and($installation->permissions)->toBe(['contents' => 'read', 'metadata' => 'read'])
            ->and($installation->events)->toBe(['push'])
            ->and($installation->suspended_at)->toBeNull();
    });

    it('updates existing installation when installation_id matches', function (): void {
        $connection = Connection::factory()->create();
        $existingInstallation = Installation::factory()->forConnection($connection)->create([
            'installation_id' => 77777777,
            'account_login' => 'old-login',
            'status' => InstallationStatus::Suspended,
        ]);

        $installationData = [
            'account' => [
                'type' => 'User',
                'login' => 'new-login',
                'avatar_url' => 'https://example.com/new-avatar.png',
            ],
            'permissions' => ['contents' => 'write'],
            'events' => ['pull_request'],
        ];

        $installation = $this->action->fromGitHub($connection, 77777777, $installationData);

        expect($installation->id)->toBe($existingInstallation->id)
            ->and($installation->account_login)->toBe('new-login')
            ->and($installation->status)->toBe(InstallationStatus::Active)
            ->and($installation->suspended_at)->toBeNull();

        $this->assertDatabaseCount('installations', 1);
    });

    it('handles missing avatar_url gracefully', function (): void {
        $connection = Connection::factory()->create();

        $installationData = [
            'account' => [
                'type' => 'User',
                'login' => 'no-avatar-user',
            ],
            'permissions' => [],
            'events' => [],
        ];

        $installation = $this->action->fromGitHub($connection, 11111111, $installationData);

        expect($installation->account_avatar_url)->toBeNull();
    });

    it('handles missing permissions and events gracefully', function (): void {
        $connection = Connection::factory()->create();

        $installationData = [
            'account' => [
                'type' => 'User',
                'login' => 'minimal-user',
            ],
        ];

        $installation = $this->action->fromGitHub($connection, 22222222, $installationData);

        expect($installation->permissions)->toBe([])
            ->and($installation->events)->toBe([]);
    });
});

describe('fromWebhook', function (): void {
    it('creates a connection and installation from webhook data', function (): void {
        $workspace = Workspace::factory()->create();

        $webhookData = [
            'installation_id' => 33333333,
            'account_type' => 'Organization',
            'account_login' => 'webhook-org',
            'account_avatar_url' => 'https://example.com/webhook-avatar.png',
            'permissions' => ['contents' => 'read', 'pull_requests' => 'write'],
            'events' => ['pull_request', 'push'],
        ];

        $installation = $this->action->fromWebhook($workspace, $webhookData);

        expect($installation)->toBeInstanceOf(Installation::class)
            ->and($installation->workspace_id)->toBe($workspace->id)
            ->and($installation->installation_id)->toBe(33333333)
            ->and($installation->account_type)->toBe('Organization')
            ->and($installation->account_login)->toBe('webhook-org')
            ->and($installation->status)->toBe(InstallationStatus::Active);

        $connection = Connection::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        expect($connection)->not->toBeNull()
            ->and($connection->status)->toBe(ConnectionStatus::Active)
            ->and($connection->external_id)->toBe('33333333');
    });

    it('reuses existing connection for workspace and provider', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::query()->where('type', ProviderType::GitHub)->firstOrFail();

        $existingConnection = Connection::factory()
            ->forWorkspace($workspace)
            ->forProvider($provider)
            ->active()
            ->create();

        $webhookData = [
            'installation_id' => 44444444,
            'account_type' => 'User',
            'account_login' => 'webhook-user',
            'account_avatar_url' => null,
            'permissions' => [],
            'events' => [],
        ];

        $installation = $this->action->fromWebhook($workspace, $webhookData);

        expect($installation->connection_id)->toBe($existingConnection->id);

        $this->assertDatabaseCount('connections', 1);
    });

    it('activates an inactive existing connection', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::query()->where('type', ProviderType::GitHub)->firstOrFail();

        $connection = Connection::factory()
            ->forWorkspace($workspace)
            ->forProvider($provider)
            ->disconnected()
            ->create();

        $webhookData = [
            'installation_id' => 55555555,
            'account_type' => 'User',
            'account_login' => 'reactivated-user',
            'account_avatar_url' => null,
            'permissions' => [],
            'events' => [],
        ];

        $this->action->fromWebhook($workspace, $webhookData);

        $connection->refresh();

        expect($connection->status)->toBe(ConnectionStatus::Active)
            ->and($connection->external_id)->toBe('55555555');
    });

    it('updates existing installation when installation_id matches', function (): void {
        $workspace = Workspace::factory()->create();
        $provider = Provider::query()->where('type', ProviderType::GitHub)->firstOrFail();

        $connection = Connection::factory()
            ->forWorkspace($workspace)
            ->forProvider($provider)
            ->active()
            ->create();

        Installation::factory()->forConnection($connection)->create([
            'installation_id' => 66666666,
            'account_login' => 'old-webhook-login',
        ]);

        $webhookData = [
            'installation_id' => 66666666,
            'account_type' => 'User',
            'account_login' => 'new-webhook-login',
            'account_avatar_url' => 'https://example.com/updated.png',
            'permissions' => ['contents' => 'read'],
            'events' => ['push'],
        ];

        $installation = $this->action->fromWebhook($workspace, $webhookData);

        expect($installation->account_login)->toBe('new-webhook-login');

        $this->assertDatabaseCount('installations', 1);
    });
});
