<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Enums\Workspace\ConnectionStatus;
use App\Exceptions\GitHub\InvalidInstallationStateException;
use App\Models\Connection;
use App\Models\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolveInstallationConnection
{
    /**
     * @throws InvalidInstallationStateException
     */
    public function handle(int $installationId, ?string $state): ?Connection
    {
        if ($state !== null) {
            return $this->resolveFromState($installationId, $state);
        }

        $installation = Installation::query()->where('installation_id', $installationId)->first();

        return $installation?->connection;
    }

    /**
     * @throws InvalidInstallationStateException
     */
    private function resolveFromState(int $installationId, string $state): Connection
    {
        $minimumInitiatedAt = now()->subMinutes(15);

        $pendingConnections = Connection::query()
            ->where('status', ConnectionStatus::Pending)
            ->latest('updated_at')
            ->limit(200)
            ->get();

        $connection = $pendingConnections->first(function (Connection $connection) use ($minimumInitiatedAt, $state): bool {
            /** @var array<string, mixed> $metadata */
            $metadata = $connection->metadata ?? [];
            $storedState = $metadata['state'] ?? null;

            if (! is_string($storedState) || ! hash_equals($storedState, $state)) {
                return false;
            }

            $initiatedAt = $metadata['initiated_at'] ?? null;

            if (is_string($initiatedAt)) {
                try {
                    return Carbon::parse($initiatedAt)->greaterThanOrEqualTo($minimumInitiatedAt);
                } catch (Throwable) {
                    return false;
                }
            }

            return $connection->updated_at?->greaterThanOrEqualTo($minimumInitiatedAt) ?? false;
        });

        if ($connection instanceof Connection) {
            return $connection;
        }

        Log::warning('GitHub installation failed - invalid/expired state', [
            'github_installation_id' => $installationId,
        ]);

        throw new InvalidInstallationStateException('Invalid or expired state parameter.');
    }
}
