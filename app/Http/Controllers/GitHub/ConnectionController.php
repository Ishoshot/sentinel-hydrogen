<?php

declare(strict_types=1);

namespace App\Http\Controllers\GitHub;

use App\Actions\GitHub\DisconnectGitHubConnection;
use App\Actions\GitHub\HandleGitHubInstallation;
use App\Actions\GitHub\InitiateGitHubConnection;
use App\Enums\ProviderType;
use App\Http\Resources\ConnectionResource;
use App\Models\Connection;
use App\Models\Provider;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ConnectionController
{
    /**
     * Get the current GitHub connection status for a workspace.
     */
    public function show(Workspace $workspace): JsonResponse
    {
        Gate::authorize('viewAny', [Connection::class, $workspace]);

        $provider = Provider::where('type', ProviderType::GitHub)->first();

        if ($provider === null) {
            return response()->json([
                'data' => null,
                'message' => 'GitHub provider not configured.',
            ]);
        }

        $connection = Connection::with(['installation' => function (\Illuminate\Database\Eloquent\Relations\HasOne $query): void {
            $query->withCount('repositories');
        }])
            ->where('workspace_id', $workspace->id)
            ->where('provider_id', $provider->id)
            ->all();

        return response()->json([
            'data' => $connection !== null ? new ConnectionResource($connection) : null,
        ]);
    }

    /**
     * Initiate a GitHub connection for a workspace.
     */
    public function store(
        Workspace $workspace,
        InitiateGitHubConnection $initiateConnection,
    ): JsonResponse {
        Gate::authorize('create', [Connection::class, $workspace]);

        $result = $initiateConnection->handle($workspace);

        if ($result['installation_url'] === '') {
            return response()->json([
                'data' => new ConnectionResource($result['connection']),
                'message' => 'GitHub connection already active.',
            ]);
        }

        return response()->json([
            'data' => new ConnectionResource($result['connection']),
            'installation_url' => $result['installation_url'],
            'message' => 'Redirect to GitHub to install the app.',
        ]);
    }

    /**
     * Handle the GitHub App installation callback.
     */
    public function callback(
        Request $request,
        HandleGitHubInstallation $handleInstallation,
    ): RedirectResponse {
        $installationId = $request->query('installation_id');
        $state = $request->query('state');
        $setupAction = $request->query('setup_action');

        if ($installationId === null) {
            return $this->redirectToError('Missing installation ID.');
        }

        // If setup_action is request, user denied the installation
        if ($setupAction === 'request') {
            return $this->redirectToError('Installation was cancelled.');
        }

        $result = $handleInstallation->handle(
            installationId: (int) $installationId,
            state: is_string($state) ? $state : null,
        );

        $workspace = $result['connection']->workspace;

        if ($workspace === null) {
            return $this->redirectToError('Workspace not found.');
        }

        return redirect()->to(sprintf('/workspaces/%s/settings/integrations', $workspace->slug))
            ->with('success', 'GitHub connected successfully!');
    }

    /**
     * Disconnect GitHub from a workspace.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        DisconnectGitHubConnection $disconnectConnection,
    ): JsonResponse {
        $provider = Provider::where('type', ProviderType::GitHub)->firstOrFail();

        $connection = Connection::where('workspace_id', $workspace->id)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        Gate::authorize('delete', $connection);

        $disconnectConnection->handle($connection, $request->user());

        return response()->json([
            'message' => 'GitHub disconnected successfully.',
        ]);
    }

    /**
     * Redirect to error page with message.
     */
    private function redirectToError(string $message): RedirectResponse
    {
        return redirect()->to('/workspaces')
            ->with('error', $message);
    }
}
