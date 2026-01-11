<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\ConnectionStatus;
use App\Enums\InstallationStatus;
use App\Enums\ProviderType;
use App\Exceptions\GitHub\InvalidInstallationStateException;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Workspace;
use App\Services\GitHub\GitHubApiService;
use Illuminate\Support\Facades\DB;

final readonly class HandleGitHubInstallation
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiService $gitHubApiService,
        private SyncInstallationRepositories $syncRepositories,
        private LogActivity $logActivity,
    ) {}

    /**
     * Handle a GitHub App installation callback.
     *
     * @param  array<string, mixed>|null  $installationData  Installation data from webhook (optional)
     * @return array{connection: Connection, installation: Installation}
     *
     * @throws InvalidInstallationStateException If state is invalid
     */
    public function handle(
        int $installationId,
        ?string $state = null,
        ?array $installationData = null
    ): array {
        return DB::transaction(function () use ($installationId, $state, $installationData): array {
            // Find the connection by state if provided
            $connection = null;

            if ($state !== null) {
                $connection = Connection::whereJsonContains('metadata->state', $state)
                    ->where('status', ConnectionStatus::Pending)
                    ->first();

                if ($connection === null) {
                    throw new InvalidInstallationStateException('Invalid or expired state parameter.');
                }
            }

            // If no state (webhook flow), find by installation_id
            if ($connection === null) {
                $installation = Installation::where('installation_id', $installationId)->first();

                if ($installation !== null) {
                    $connection = $installation->connection;
                }
            }

            // Fetch installation data from GitHub if not provided
            if ($installationData === null) {
                $installationData = $this->gitHubApiService->getInstallation($installationId);
            }

            /** @var array{type: string, login: string, avatar_url?: string|null} $account */
            $account = $installationData['account'];

            // If still no connection, we can't proceed (orphan installation)
            if ($connection === null) {
                throw new InvalidInstallationStateException('No connection found for this installation.');
            }

            /** @var array<string, mixed> $existingMetadata */
            $existingMetadata = $connection->metadata ?? [];

            // Update connection to active
            $connection->update([
                'status' => ConnectionStatus::Active,
                'external_id' => (string) $installationId,
                'metadata' => array_merge($existingMetadata, [
                    'connected_at' => now()->toIso8601String(),
                ]),
            ]);

            /** @var array<string, string> $permissions */
            $permissions = $installationData['permissions'] ?? [];

            /** @var array<int, string> $events */
            $events = $installationData['events'] ?? [];

            // Create or update installation record
            $installation = Installation::updateOrCreate(
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

            // Sync repositories after installation
            $this->syncRepositories->handle($installation);

            // Log activity
            $workspace = $connection->workspace;
            if ($workspace !== null) {
                $this->logActivity->handle(
                    workspace: $workspace,
                    type: ActivityType::GitHubConnected,
                    description: sprintf('GitHub connected via %s', $account['login']),
                    subject: $installation,
                    metadata: [
                        'account_login' => $account['login'],
                        'account_type' => $account['type'],
                    ],
                );
            }

            return [
                'connection' => $connection->refresh(),
                'installation' => $installation->refresh(),
            ];
        });
    }

    /**
     * Handle installation from webhook event (without state validation).
     *
     * @param  array<string, mixed>  $webhookData  Parsed webhook payload
     */
    public function executeFromWebhook(int $workspaceId, array $webhookData): Installation
    {
        return DB::transaction(function () use ($workspaceId, $webhookData): Installation {
            $workspace = Workspace::findOrFail($workspaceId);
            $provider = Provider::where('type', ProviderType::GitHub)->firstOrFail();

            /** @var int|string $installationIdRaw */
            $installationIdRaw = $webhookData['installation_id'] ?? '';
            $externalId = (string) $installationIdRaw;

            // Get or create connection
            $connection = Connection::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'provider_id' => $provider->id,
                ],
                [
                    'status' => ConnectionStatus::Active,
                    'external_id' => $externalId,
                ]
            );

            // Update to active if not already
            if (! $connection->isActive()) {
                $connection->update([
                    'status' => ConnectionStatus::Active,
                    'external_id' => $externalId,
                ]);
            }

            // Create or update installation
            $installation = Installation::updateOrCreate(
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

            return $installation;
        });
    }
}
