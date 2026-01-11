<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Run\ListWorkspaceRunsRequest;
use App\Http\Resources\RunResource;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ListWorkspaceRunsController
{
    /**
     * List all runs for a workspace across all repositories.
     */
    public function __invoke(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Run::class, $workspace]);

        $query = Run::query()
            ->where('workspace_id', $workspace->id)
            ->with(['repository:id,name,full_name,private,language'])
            ->withCount('findings');

        $this->applyFilters($query, $request, $workspace);
        $this->applySorting($query, $request);

        $runs = $query->paginate($request->perPage());

        return RunResource::collection($runs);
    }

    /**
     * Apply filters to the runs query.
     *
     * @param  Builder<Run>  $query
     */
    private function applyFilters(
        Builder $query,
        ListWorkspaceRunsRequest $request,
        Workspace $workspace
    ): void {
        $validated = $request->validated();

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['repository_id'])) {
            $repository = Repository::query()
                ->where('id', $validated['repository_id'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($repository !== null) {
                $query->where('repository_id', $repository->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (isset($validated['risk_level'])) {
            $query->whereJsonContains('metadata->review_summary->risk_level', $validated['risk_level']);
        }

        if (isset($validated['author'])) {
            $query->where(function (Builder $q) use ($validated): void {
                $q->whereJsonContains('metadata->author->login', $validated['author'])
                    ->orWhere('metadata->sender_login', $validated['author']);
            });
        }

        if (isset($validated['from_date'])) {
            $query->whereDate('created_at', '>=', $validated['from_date']);
        }

        if (isset($validated['to_date'])) {
            $query->whereDate('created_at', '<=', $validated['to_date']);
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('metadata->pull_request_title', 'like', "%{$search}%")
                    ->orWhere('metadata->sender_login', 'like', "%{$search}%")
                    ->orWhereHas('repository', function (Builder $repoQuery) use ($search): void {
                        $repoQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }
    }

    /**
     * Apply sorting to the runs query.
     *
     * @param  Builder<Run>  $query
     */
    private function applySorting(
        Builder $query,
        ListWorkspaceRunsRequest $request
    ): void {
        $sortBy = $request->sortBy();
        $sortOrder = $request->sortOrder();

        if ($sortBy === 'findings_count') {
            $query->orderBy('findings_count', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
    }
}
