<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Handlers;

use App\Enums\GitHub\InstallationStatus;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Installation;

final class InstallationWebhookLifecycleHandler
{
    /**
     * Mark installation as uninstalled and disconnect related connection.
     */
    public function markUninstalled(Installation $installation): void
    {
        $installation->update([
            'status' => InstallationStatus::Uninstalled,
        ]);

        $connection = $installation->connection;

        if ($connection === null) {
            return;
        }

        /** @var array<string, mixed> $existingMetadata */
        $existingMetadata = $connection->metadata ?? [];

        $connection->update([
            'status' => ConnectionStatus::Disconnected,
            'metadata' => array_merge($existingMetadata, [
                'uninstalled_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Mark installation as suspended.
     */
    public function markSuspended(Installation $installation): void
    {
        $installation->update([
            'status' => InstallationStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    /**
     * Mark installation as active from suspended state.
     */
    public function markUnsuspended(Installation $installation): void
    {
        $installation->update([
            'status' => InstallationStatus::Active,
            'suspended_at' => null,
        ]);
    }
}
