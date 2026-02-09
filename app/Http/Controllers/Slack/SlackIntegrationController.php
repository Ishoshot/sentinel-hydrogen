<?php

declare(strict_types=1);

namespace App\Http\Controllers\Slack;

use App\Actions\Slack\DisconnectSlack;
use App\Actions\Slack\InitiateSlackConnection;
use App\Http\Resources\SlackIntegrationResource;
use App\Models\SlackIntegration;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SlackIntegrationController
{
    /**
     * Get the current Slack integration status for a workspace.
     */
    public function show(Workspace $workspace): JsonResponse
    {
        Gate::authorize('viewAny', [SlackIntegration::class, $workspace]);

        $integration = SlackIntegration::query()
            ->forWorkspace($workspace)
            ->first();

        return response()->json([
            'data' => $integration !== null ? new SlackIntegrationResource($integration) : null,
        ]);
    }

    /**
     * Initiate Slack OAuth connection for a workspace.
     */
    public function store(
        Workspace $workspace,
        InitiateSlackConnection $initiateConnection,
    ): JsonResponse {
        Gate::authorize('create', [SlackIntegration::class, $workspace]);

        $result = $initiateConnection->handle($workspace);

        return response()->json([
            'data' => new SlackIntegrationResource($result['integration']),
            'oauth_url' => $result['oauth_url'],
        ]);
    }

    /**
     * Disconnect Slack from a workspace.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        DisconnectSlack $disconnectSlack,
    ): JsonResponse {
        $integration = SlackIntegration::query()
            ->forWorkspace($workspace)
            ->firstOrFail();

        Gate::authorize('delete', $integration);

        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $disconnectSlack->handle($workspace, $user);

        return response()->json([
            'message' => 'Slack disconnected successfully.',
        ]);
    }
}
