<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProviderKeys\DeleteProviderKey;
use App\Actions\ProviderKeys\StoreProviderKey;
use App\Actions\ProviderKeys\UpdateProviderKeyModel;
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
            ->with('providerModel')
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

        /** @var array{provider: string, key: string, provider_model_id?: int|null} $validated */
        $validated = $request->validated();

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        try {
            $providerKey = $storeAction->handle(
                repository: $repository,
                provider: AiProvider::from($validated['provider']),
                key: $validated['key'],
                actor: $user,
                providerModelId: $validated['provider_model_id'] ?? null,
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
     * Update the AI model for a provider key.
     */
    public function update(
        Request $request,
        Workspace $workspace,
        Repository $repository,
        ProviderKey $providerKey,
        UpdateProviderKeyModel $updateAction,
    ): JsonResponse {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($providerKey->repository_id !== $repository->id) {
            abort(404);
        }

        Gate::authorize('update', $providerKey);

        /** @var array{provider_model_id?: int|null} $validated */
        $validated = $request->validate([
            'provider_model_id' => ['nullable', 'integer', 'exists:provider_models,id'],
        ]);

        try {
            $updatedKey = $updateAction->handle(
                providerKey: $providerKey,
                providerModelId: $validated['provider_model_id'] ?? null,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json([
                'message' => $invalidArgumentException->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new ProviderKeyResource($updatedKey->load('providerModel')),
            'message' => 'AI model updated successfully.',
        ]);
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

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $deleteAction->handle($providerKey, $user);

        return response()->json([
            'message' => 'Provider key deleted successfully.',
        ]);
    }
}
