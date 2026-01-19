<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Enums\ConnectionStatus;
use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Provider;
use App\Models\Workspace;
use App\Services\GitHub\GitHubAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class InitiateGitHubConnection
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubAppService $gitHubAppService
    ) {}

    /**
     * Initiate a GitHub connection for a workspace.
     *
     * @return array{connection: Connection, installation_url: string, state: string}
     */
    public function handle(Workspace $workspace): array
    {
        return DB::transaction(function () use ($workspace): array {
            $provider = Provider::where('type', ProviderType::GitHub)->firstOrFail();

            // Check for existing connection
            $existingConnection = Connection::with('installation')
                ->where('workspace_id', $workspace->id)
                ->where('provider_id', $provider->id)
                ->first();

            if ($existingConnection !== null) {
                // If active, return configuration URL to add/remove repositories
                if ($existingConnection->isActive() && $existingConnection->installation !== null) {
                    $installation = $existingConnection->installation;
                    $configureUrl = $this->gitHubAppService->getInstallationConfigureUrl(
                        $installation->installation_id,
                        $installation->account_login,
                        $installation->isOrganization()
                    );

                    return [
                        'connection' => $existingConnection,
                        'installation_url' => $configureUrl,
                        'state' => '',
                    ];
                }

                // If pending or failed, update to pending and generate new state
                $existingConnection->update([
                    'status' => ConnectionStatus::Pending,
                ]);

                $connection = $existingConnection;
            } else {
                // Create new pending connection
                $connection = Connection::create([
                    'workspace_id' => $workspace->id,
                    'provider_id' => $provider->id,
                    'status' => ConnectionStatus::Pending,
                    'metadata' => null,
                ]);
            }

            // Generate state for callback verification
            $state = Str::random(40);

            // Store state in connection metadata
            $connection->update([
                'metadata' => [
                    'state' => $state,
                    'initiated_at' => now()->toIso8601String(),
                ],
            ]);

            $installationUrl = $this->gitHubAppService->getInstallationUrl($state);

            return [
                'connection' => $connection->refresh(),
                'installation_url' => $installationUrl,
                'state' => $state,
            ];
        });
    }
}
