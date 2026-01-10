<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\RunResource;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class RunController
{
    /**
     * List runs for a repository.
     */
    public function index(Request $request, Workspace $workspace, Repository $repository): AnonymousResourceCollection
    {
        if ($repository->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('viewAny', [Run::class, $workspace]);
        Gate::authorize('view', $repository);

        $perPage = min((int) $request->query('per_page', '20'), 100);

        $runs = Run::query()
            ->where('workspace_id', $workspace->id)
            ->where('repository_id', $repository->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return RunResource::collection($runs);
    }

    /**
     * Show a run with findings.
     */
    public function show(Workspace $workspace, Run $run): JsonResponse
    {
        if ($run->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $run);

        $run->load(['repository', 'findings.annotations']);

        return response()->json([
            'data' => new RunResource($run),
        ]);
    }
}
