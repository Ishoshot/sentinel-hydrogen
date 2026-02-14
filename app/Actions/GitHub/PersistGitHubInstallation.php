<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Enums\Auth\ProviderType;
use App\Enums\GitHub\InstallationStatus;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Workspace;

final class PersistGitHubInstallation
{
    /**
     * @param  array<string, mixed>  $installationData
     */
    public function fromGitHub(Connection $connection, int $installationId, array $installationData): Installation
    {
        /** @var array<string, mixed> $existingMetadata */
        $existingMetadata = $connection->metadata ?? [];

        $connection->update([
            'status' => ConnectionStatus::Active,
            'external_id' => (string) $installationId,
            'metadata' => array_merge($existingMetadata, [
                'connected_at' => now()->toIso8601String(),
            ]),
        ]);

        /** @var array{type: string, login: string, avatar_url?: string|null} $account */
        $account = $installationData['account'];

        /** @var array<string, string> $permissions */
        $permissions = $installationData['permissions'] ?? [];

        /** @var array<int, string> $events */
        $events = $installationData['events'] ?? [];

        return Installation::query()->updateOrCreate(
            ['installation_id' => $installationId],
            [
                'connection_id' => $connection->id,
                'workspace_id' => $connection->workspace_id,
                'account_type' => $account['type'],
                'account_login' => $account['login'],
                'account_avatar_url' => $account['avatar_url'] ?? null,
                'status' => InstallationStatus::Active,
                'permissions' => $permissions,
                'events' => $events,
                'suspended_at' => null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $webhookData
     */
    public function fromWebhook(Workspace $workspace, array $webhookData): Installation
    {
        $provider = Provider::query()->where('type', ProviderType::GitHub)->firstOrFail();

        /** @var int|string $installationIdRaw */
        $installationIdRaw = $webhookData['installation_id'] ?? '';
        $externalId = (string) $installationIdRaw;

        $connection = Connection::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider_id' => $provider->id,
            ],
            [
                'status' => ConnectionStatus::Active,
                'external_id' => $externalId,
            ]
        );

        if (! $connection->isActive()) {
            $connection->update([
                'status' => ConnectionStatus::Active,
                'external_id' => $externalId,
            ]);
        }

        return Installation::query()->updateOrCreate(
            ['installation_id' => $webhookData['installation_id']],
            [
                'connection_id' => $connection->id,
                'workspace_id' => $workspace->id,
                'account_type' => $webhookData['account_type'],
                'account_login' => $webhookData['account_login'],
                'account_avatar_url' => $webhookData['account_avatar_url'],
                'status' => InstallationStatus::Active,
                'permissions' => $webhookData['permissions'] ?? [],
                'events' => $webhookData['events'] ?? [],
                'suspended_at' => null,
            ]
        );
    }
}
