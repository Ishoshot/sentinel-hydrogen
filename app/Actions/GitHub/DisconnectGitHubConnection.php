<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Connection;
use App\Models\User;
use App\Services\GitHub\GitHubAppService;
use Illuminate\Support\Facades\DB;

final readonly class DisconnectGitHubConnection
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubAppService $gitHubAppService,
        private LogActivity $logActivity,
    ) {}

    /**
     * Disconnect a GitHub connection.
     */
    public function handle(Connection $connection, ?User $actor = null): void
    {
        DB::transaction(function () use ($connection, $actor): void {
            // Clear cached installation token if exists
            $installation = $connection->installation;
            $accountLogin = $installation->account_login ?? 'Unknown';

            if ($installation !== null) {
                $this->gitHubAppService->clearInstallationToken($installation->installation_id);
            }

            /** @var array<string, mixed> $existingMetadata */
            $existingMetadata = $connection->metadata ?? [];

            // Update connection status
            $connection->update([
                'status' => ConnectionStatus::Disconnected,
                'metadata' => array_merge($existingMetadata, [
                    'disconnected_at' => now()->toIso8601String(),
                ]),
            ]);

            // Log activity
            $workspace = $connection->workspace;
            if ($workspace !== null) {
                $this->logActivity->handle(
                    workspace: $workspace,
                    type: ActivityType::GitHubDisconnected,
                    description: 'GitHub disconnected from '.$accountLogin,
                    actor: $actor,
                    subject: $connection,
                    metadata: ['account_login' => $accountLogin],
                );
            }

            // Note: We don't delete the installation or repositories
            // to preserve history. They'll be cleaned up on reconnection
            // or can be manually purged.
        });
    }
}
