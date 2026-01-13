<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProviderKeys\DeleteProviderKey;
use App\Actions\ProviderKeys\StoreProviderKey;
use App\Enums\AiProvider;
use App\Http\Requests\ProviderKey\StoreProviderKeyRequest;
use App\Http\Resources\ProviderKeyResource;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ProviderKeyController
{
    /**
     * List all provider keys for a repository.
     *
     * SECURITY: encrypted_key is never returned (model $hidden).
     */
    public function index(Workspace $workspace, Repository $repository): JsonResponse
    {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $repository);

        $keys = $repository->providerKeys()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => ProviderKeyResource::collection($keys),
        ]);
    }

    /**
     * Store or update a provider key for a repository.
     *
     * Creates a new key or updates the existing key for the same provider.
     */
    public function store(
        StoreProviderKeyRequest $request,
        Workspace $workspace,
        Repository $repository,
        StoreProviderKey $storeAction,
    ): JsonResponse {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        /** @var array{provider: string, key: string} $validated */
        $validated = $request->validated();

        try {
            $providerKey = $storeAction->handle(
                repository: $repository,
                provider: AiProvider::from($validated['provider']),
                key: $validated['key'],
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json([
                'message' => $invalidArgumentException->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new ProviderKeyResource($providerKey),
            'message' => 'Provider key configured successfully.',
        ], 201);
    }

    /**
     * Delete a provider key.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        Repository $repository,
        ProviderKey $providerKey,
        DeleteProviderKey $deleteAction,
    ): JsonResponse {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($providerKey->repository_id !== $repository->id) {
            abort(404);
        }

        Gate::authorize('delete', $providerKey);

        $deleteAction->handle($providerKey, $request->user());

        return response()->json([
            'message' => 'Provider key deleted successfully.',
        ]);
    }
}
