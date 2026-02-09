<?php

declare(strict_types=1);

namespace App\Actions\Slack;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Exceptions\Slack\InvalidSlackOAuthStateException;
use App\Models\SlackIntegration;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Log;

final readonly class HandleSlackCallback
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private SlackServiceContract $slackService,
        private LogActivity $logActivity,
    ) {}

    /**
     * Handle the Slack OAuth callback.
     *
     * @throws InvalidSlackOAuthStateException
     */
    public function handle(string $code, string $state): SlackIntegration
    {
        $integration = $this->findIntegrationByState($state);

        /** @var string $redirectUri */
        $redirectUri = config('slack.redirect_uri');

        $tokenData = $this->slackService->exchangeCodeForToken($code, $redirectUri);

        $integration->update([
            'access_token' => $tokenData['access_token'],
            'bot_user_id' => $tokenData['bot_user_id'],
            'slack_team_id' => $tokenData['team']['id'],
            'team_name' => $tokenData['team']['name'],
            'scope' => $tokenData['scope'],
            'authed_user_id' => $tokenData['authed_user']['id'],
            'is_active' => true,
            'connected_at' => now(),
            'state' => null,
            'state_expires_at' => null,
        ]);

        /** @var \App\Models\Workspace $workspace */
        $workspace = $integration->workspace;

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::SlackConnected,
            description: sprintf('Slack connected via %s', $tokenData['team']['name']),
            subject: $integration,
            metadata: [
                'slack_team_id' => $tokenData['team']['id'],
                'team_name' => $tokenData['team']['name'],
            ],
        );

        return $integration->refresh();
    }

    /**
     * Find the integration matching the given state using constant-time comparison.
     *
     * @throws InvalidSlackOAuthStateException
     */
    private function findIntegrationByState(string $state): SlackIntegration
    {
        $pendingIntegrations = SlackIntegration::query()
            ->whereNotNull('state')
            ->where('state_expires_at', '>=', now())
            ->limit(100)
            ->get();

        /** @var SlackIntegration|null $integration */
        $integration = $pendingIntegrations->first(function (SlackIntegration $integration) use ($state): bool {
            /** @var string $knownState */
            $knownState = $integration->state;

            return hash_equals($knownState, $state);
        });

        if ($integration === null) {
            Log::warning('Slack OAuth callback failed - invalid/expired state');

            throw new InvalidSlackOAuthStateException;
        }

        return $integration;
    }
}
