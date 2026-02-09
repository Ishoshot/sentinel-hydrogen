<?php

declare(strict_types=1);

namespace App\Http\Controllers\Slack;

use App\Actions\Slack\UpdateSlackChannel;
use App\Http\Requests\Slack\UpdateSlackChannelRequest;
use App\Http\Resources\SlackIntegrationResource;
use App\Models\SlackIntegration;
use App\Models\Workspace;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class SlackChannelController
{
    /**
     * List available Slack channels for the workspace integration.
     */
    public function index(
        Workspace $workspace,
        SlackServiceContract $slackService,
    ): JsonResponse {
        Gate::authorize('viewAny', [SlackIntegration::class, $workspace]);

        $integration = SlackIntegration::query()
            ->forWorkspace($workspace)
            ->active()
            ->firstOrFail();

        $channels = $slackService->listChannels($integration);

        return response()->json([
            'data' => $channels,
        ]);
    }

    /**
     * Update the selected channel for the workspace Slack integration.
     */
    public function update(
        UpdateSlackChannelRequest $request,
        Workspace $workspace,
        UpdateSlackChannel $updateChannel,
    ): JsonResponse {
        $integration = SlackIntegration::query()
            ->forWorkspace($workspace)
            ->active()
            ->firstOrFail();

        Gate::authorize('update', $integration);

        $integration = $updateChannel->handle(
            integration: $integration,
            channelId: $request->string('channel_id')->toString(),
            channelName: $request->string('channel_name')->toString(),
        );

        return response()->json([
            'data' => new SlackIntegrationResource($integration),
            'message' => 'Slack channel updated successfully.',
        ]);
    }
}
