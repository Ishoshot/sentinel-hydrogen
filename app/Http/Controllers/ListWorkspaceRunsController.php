<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Runs\ListWorkspaceRuns;
use App\Http\Requests\Run\ListWorkspaceRunsRequest;
use App\Http\Resources\PullRequestGroupResource;
use App\Http\Resources\RepositoryGroupResource;
use App\Http\Resources\RunResource;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class ListWorkspaceRunsController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private ListWorkspaceRuns $listWorkspaceRuns,
    ) {}

    /**
     * List all runs for a workspace across all repositories.
     */
    public function __invoke(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Run::class, $workspace]);

        return match ($request->groupBy()) {
            'pr' => $this->getGroupedByPullRequest($request, $workspace),
            'repository' => $this->getGroupedByRepository($request, $workspace),
            default => $this->getFlat($request, $workspace),
        };
    }

    /**
     * Get flat list of runs (default view).
     */
    private function getFlat(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $runs = $this->listWorkspaceRuns->flat(
            workspace: $workspace,
            filters: $request->filters(),
            sortBy: $request->sortBy(),
            sortOrder: $request->sortOrder(),
            perPage: $request->perPage(),
        );

        return RunResource::collection($runs);
    }

    /**
     * Get runs grouped by pull request.
     */
    private function getGroupedByPullRequest(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $result = $this->listWorkspaceRuns->groupedByPullRequest(
            workspace: $workspace,
            filters: $request->filters(),
            perPage: $request->perPage(),
        );

        return PullRequestGroupResource::collection($result['groups'])->additional([
            'meta' => $result['pagination'],
        ]);
    }

    /**
     * Get runs grouped by repository.
     */
    private function getGroupedByRepository(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $result = $this->listWorkspaceRuns->groupedByRepository(
            workspace: $workspace,
            filters: $request->filters(),
            perPage: $request->perPage(),
        );

        return RepositoryGroupResource::collection($result['groups'])->additional([
            'meta' => $result['pagination'],
        ]);
    }
}
