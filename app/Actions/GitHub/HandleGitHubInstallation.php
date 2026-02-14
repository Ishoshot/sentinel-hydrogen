<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Exceptions\GitHub\InvalidInstallationStateException;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class HandleGitHubInstallation
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private ResolveInstallationConnection $resolveInstallationConnection,
        private PersistGitHubInstallation $persistGitHubInstallation,
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
        ?array $installationData = null,
    ): array {
        return DB::transaction(function () use ($installationId, $state, $installationData): array {
            $connection = $this->resolveInstallationConnection->handle($installationId, $state);

            if ($installationData === null) {
                $installationData = $this->gitHubApiService->getInstallation($installationId);
            }

            /** @var array{type: string, login: string, avatar_url?: string|null} $account */
            $account = $installationData['account'];

            if (! $connection instanceof Connection) {
                Log::warning('GitHub installation failed - orphan installation', [
                    'github_installation_id' => $installationId,
                ]);

                throw new InvalidInstallationStateException('No connection found for this installation.');
            }

            $installation = $this->persistGitHubInstallation->fromGitHub($connection, $installationId, $installationData);

            $this->syncRepositories->handle($installation);

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
            $workspace = Workspace::query()->findOrFail($workspaceId);

            return $this->persistGitHubInstallation->fromWebhook($workspace, $webhookData);
        });
    }
}
