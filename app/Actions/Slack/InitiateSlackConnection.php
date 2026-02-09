<?php

declare(strict_types=1);

namespace App\Actions\Slack;

use App\Models\SlackIntegration;
use App\Models\Workspace;
use Illuminate\Support\Str;

final readonly class InitiateSlackConnection
{
    /**
     * Initiate a Slack OAuth connection for a workspace.
     *
     * @return array{integration: SlackIntegration, oauth_url: string}
     */
    public function handle(Workspace $workspace): array
    {
        $state = Str::random(40);
        $stateExpiresAt = now()->addMinutes(15);

        $integration = SlackIntegration::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'state' => $state,
                'state_expires_at' => $stateExpiresAt,
            ],
        );

        /** @var string $authorizeUrl */
        $authorizeUrl = config('slack.oauth_authorize_url');

        $oauthUrl = $authorizeUrl.'?'.http_build_query([
            'client_id' => config('slack.client_id'),
            'scope' => config('slack.scopes'),
            'redirect_uri' => config('slack.redirect_uri'),
            'state' => $state,
        ]);

        return [
            'integration' => $integration,
            'oauth_url' => $oauthUrl,
        ];
    }
}
