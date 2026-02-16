<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProviderKeys;

use App\Actions\ProviderKeys\StoreWorkspaceProviderKey;
use App\Enums\AI\AiProvider;
use App\Http\Requests\ProviderKey\StoreWorkspaceProviderKeyRequest;
use App\Http\Resources\ProviderKeyResource;
use App\Models\ProviderKey;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class WorkspaceProviderKeyController
{
    /**
     * List workspace-level provider keys.
     */
    public function index(Workspace $workspace): JsonResponse
    {
        Gate::authorize('viewAny', [ProviderKey::class, $workspace]);

        $keys = $workspace->workspaceProviderKeys()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => ProviderKeyResource::collection($keys),
        ]);
    }

    /**
     * Store or update a workspace-level provider key.
     */
    public function store(
        StoreWorkspaceProviderKeyRequest $request,
        Workspace $workspace,
        StoreWorkspaceProviderKey $storeAction,
    ): JsonResponse {
        /** @var array{provider: string, key: string} $validated */
        $validated = $request->validated();

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $providerKey = $storeAction->handle(
            workspace: $workspace,
            provider: AiProvider::from($validated['provider']),
            key: $validated['key'],
            actor: $user,
        );

        return response()->json([
            'data' => new ProviderKeyResource($providerKey),
            'message' => 'Workspace provider key configured successfully.',
        ], 201);
    }

    /**
     * Delete a workspace-level provider key.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        ProviderKey $providerKey,
    ): JsonResponse {
        if ($providerKey->workspace_id !== $workspace->id || ! $providerKey->isWorkspaceLevel()) {
            abort(404);
        }

        Gate::authorize('delete', $providerKey);

        $providerKey->delete();

        return response()->json([
            'message' => 'Workspace provider key deleted successfully.',
        ]);
    }
}
