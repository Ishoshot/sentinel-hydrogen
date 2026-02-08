<?php

declare(strict_types=1);

namespace App\Http\Controllers\GitHub;

use App\Actions\Activities\LogActivity;
use App\Actions\GitHub\CreateConfigPullRequest;
use App\Actions\GitHub\SyncInstallationRepositories;
use App\Actions\GitHub\UpdateRepositorySettings;
use App\Enums\Workspace\ActivityType;
use App\Http\Requests\GitHub\IndexRepositoriesRequest;
use App\Http\Requests\GitHub\UpdateRepositorySettingsRequest;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class RepositoryController
{
    /**
     * List all repositories for a workspace.
     */
    public function index(IndexRepositoriesRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Repository::class, $workspace]);

        $repositories = Repository::with(['settings', 'providerKeys.providerModel'])
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->paginate($request->perPage());

        return RepositoryResource::collection($repositories);
    }

    /**
     * Get a specific repository.
     */
    public function show(Workspace $workspace, Repository $repository): JsonResponse
    {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $repository);

        $repository->load(['settings', 'installation', 'providerKeys.providerModel']);

        return response()->json([
            'data' => new RepositoryResource($repository),
        ]);
    }

    /**
     * Update repository settings.
     */
    public function update(
        UpdateRepositorySettingsRequest $request,
        Workspace $workspace,
        Repository $repository,
        UpdateRepositorySettings $updateSettings,
    ): JsonResponse {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('update', $repository);

        /** @var array{auto_review_enabled?: bool, review_rules?: array<string, mixed>|null} $validated */
        $validated = $request->validated();

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $updateSettings->handle($repository, $validated, $user);

        $repository->refresh();
        $repository->load(['settings', 'providerKeys.providerModel']);

        return response()->json([
            'data' => new RepositoryResource($repository),
            'message' => 'Repository settings updated successfully.',
        ]);
    }

    /**
     * Sync repositories from GitHub.
     */
    public function sync(
        Request $request,
        Workspace $workspace,
        SyncInstallationRepositories $syncRepositories,
        LogActivity $logActivity,
    ): JsonResponse {
        Gate::authorize('sync', [Repository::class, $workspace]);

        $installation = $workspace->installation;

        if ($installation === null) {
            return response()->json([
                'message' => 'No GitHub installation found. Please connect GitHub first.',
            ], 422);
        }

        $result = $syncRepositories->handle($installation);

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RepositoriesSynced,
            description: sprintf(
                'Repositories synced: %d added, %d updated, %d removed',
                $result['added'],
                $result['updated'],
                $result['removed']
            ),
            actor: $user,
            metadata: $result,
        );

        return response()->json([
            'message' => sprintf(
                'Repositories synced successfully. Added: %d, Updated: %d, Removed: %d',
                $result['added'],
                $result['updated'],
                $result['removed']
            ),
            'summary' => $result,
        ]);
    }

    /**
     * Create a PR to add Sentinel configuration to a repository.
     */
    public function createConfigPr(
        Workspace $workspace,
        Repository $repository,
        CreateConfigPullRequest $action,
    ): JsonResponse {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('update', $repository);

        $result = $action->handle($repository);

        return response()->json($result->toArray());
    }
}
