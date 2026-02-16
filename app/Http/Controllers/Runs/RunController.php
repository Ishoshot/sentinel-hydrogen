<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Runs\ListRepositoryRuns;
use App\Actions\Runs\ShowRun;
use App\Http\Requests\Run\IndexRunsRequest;
use App\Http\Resources\RunResource;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class RunController
{
    /**
     * List runs for a repository.
     */
    public function index(
        IndexRunsRequest $request,
        Workspace $workspace,
        Repository $repository,
        ListRepositoryRuns $listRepositoryRuns,
    ): AnonymousResourceCollection {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('viewAny', [Run::class, $workspace]);
        Gate::authorize('view', $repository);

        $runs = $listRepositoryRuns->handle(
            workspace: $workspace,
            repository: $repository,
            perPage: $request->perPage(),
        );

        return RunResource::collection($runs);
    }

    /**
     * Show a run with findings.
     */
    public function show(
        Workspace $workspace,
        Run $run,
        ShowRun $showRun,
    ): JsonResponse {
        if ($run->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $run);

        $run = $showRun->handle($run);

        return response()->json([
            'data' => new RunResource($run),
        ]);
    }
}
