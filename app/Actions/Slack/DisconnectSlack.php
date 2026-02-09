<?php

declare(strict_types=1);

namespace App\Actions\Slack;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\SlackIntegration;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class DisconnectSlack
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private SlackServiceContract $slackService,
        private LogActivity $logActivity,
    ) {}

    /**
     * Disconnect Slack from a workspace.
     */
    public function handle(Workspace $workspace, ?User $user = null): void
    {
        $integration = SlackIntegration::query()
            ->forWorkspace($workspace)
            ->first();

        if ($integration === null) {
            return;
        }

        if ($integration->hasValidToken()) {
            try {
                $this->slackService->revokeToken($integration);
            } catch (Throwable $throwable) {
                Log::warning('Failed to revoke Slack token during disconnect', [
                    'workspace_id' => $workspace->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $teamName = $integration->team_name;

        $integration->delete();

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::SlackDisconnected,
            description: 'Slack disconnected',
            actor: $user,
            metadata: [
                'team_name' => $teamName,
            ],
        );
    }
}
