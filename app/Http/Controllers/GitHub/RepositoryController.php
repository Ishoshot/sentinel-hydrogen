<?php

declare(strict_types=1);

namespace App\Http\Controllers\GitHub;

use App\Actions\Activities\LogActivity;
use App\Actions\GitHub\SyncInstallationRepositories;
use App\Actions\GitHub\UpdateRepositorySettings;
use App\Enums\ActivityType;
use App\Http\Requests\GitHub\UpdateRepositorySettingsRequest;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class RepositoryController
{
    /**
     * List all repositories for a workspace.
     */
    public function index(Workspace $workspace): JsonResponse
    {
        Gate::authorize('viewAny', [Repository::class, $workspace]);

        $repositories = Repository::with('settings')
            ->where('workspace_id', $workspace->id)
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'data' => RepositoryResource::collection($repositories),
        ]);
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

        $repository->load('settings', 'installation');

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

        $updateSettings->handle($repository, $validated, $request->user());

        $repository->refresh();
        $repository->load('settings');

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

        $logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RepositoriesSynced,
            description: sprintf(
                'Repositories synced: %d added, %d updated, %d removed',
                $result['added'],
                $result['updated'],
                $result['removed']
            ),
            actor: $request->user(),
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
}
