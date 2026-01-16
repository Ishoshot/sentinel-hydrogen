<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Run\ListWorkspaceRunsRequest;
use App\Http\Resources\PullRequestGroupResource;
use App\Http\Resources\RepositoryGroupResource;
use App\Http\Resources\RunResource;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use stdClass;

final class ListWorkspaceRunsController
{
    private const int MAX_RUNS_PER_GROUP = 10;

    /**
     * List all runs for a workspace across all repositories.
     */
    public function __invoke(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Run::class, $workspace]);

        $groupBy = $request->groupBy();

        return match ($groupBy) {
            'pr' => $this->getGroupedByPullRequest($request, $workspace),
            'repository' => $this->getGroupedByRepository($request, $workspace),
            default => $this->getFlat($request, $workspace),
        };
    }

    /**
     * Get database-agnostic expression for effective PR number.
     */
    private function effectivePrNumberExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "COALESCE(pr_number, (metadata->>'pull_request_number')::int)",
            default => "COALESCE(pr_number, CAST(json_extract(metadata, '$.pull_request_number') AS INTEGER))",
        };
    }

    /**
     * Get database-agnostic expression for effective PR title.
     */
    private function effectivePrTitleExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "COALESCE(pr_title, metadata->>'pull_request_title')",
            default => "COALESCE(pr_title, json_extract(metadata, '$.pull_request_title'))",
        };
    }

    /**
     * Get flat list of runs (default view).
     */
    private function getFlat(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
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
     * Get runs grouped by pull request using database-level grouping.
     */
    private function getGroupedByPullRequest(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $effectivePrNumber = $this->effectivePrNumberExpression();
        $effectivePrTitle = $this->effectivePrTitleExpression();

        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereRaw($effectivePrNumber.' IS NOT NULL');

        $this->applyFilters($baseQuery, $request, $workspace);

        $paginatedGroups = DB::table('runs')
            ->whereIn('id', $baseQuery->select('id'))
            ->select([
                'repository_id',
                DB::raw($effectivePrNumber.' as pr_number'),
                DB::raw('MAX('.$effectivePrTitle.') as pr_title'),
                DB::raw('COUNT(*) as runs_count'),
                DB::raw('MAX(created_at) as latest_created_at'),
            ])
            ->groupBy('repository_id', DB::raw($effectivePrNumber))
            ->orderByDesc('latest_created_at')
            ->paginate($request->perPage());

        $prGroups = $this->hydratePrGroups($paginatedGroups, $workspace);

        return PullRequestGroupResource::collection($prGroups)->additional([
            'meta' => [
                'current_page' => $paginatedGroups->currentPage(),
                'per_page' => $paginatedGroups->perPage(),
                'total' => $paginatedGroups->total(),
                'last_page' => $paginatedGroups->lastPage(),
            ],
        ]);
    }

    /**
     * Get runs grouped by repository using database-level grouping.
     */
    private function getGroupedByRepository(ListWorkspaceRunsRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id);

        $this->applyFilters($baseQuery, $request, $workspace);

        $effectivePrNumber = $this->effectivePrNumberExpression();

        $paginatedRepos = DB::table('runs')
            ->whereIn('id', $baseQuery->select('id'))
            ->select([
                'repository_id',
                DB::raw('COUNT(*) as runs_count'),
                DB::raw('COUNT(DISTINCT '.$effectivePrNumber.') as pull_requests_count'),
            ])
            ->groupBy('repository_id')
            ->orderByDesc('runs_count')
            ->paginate($request->perPage());

        $repoGroups = $this->hydrateRepositoryGroups($paginatedRepos, $request, $workspace);

        return RepositoryGroupResource::collection($repoGroups)->additional([
            'meta' => [
                'current_page' => $paginatedRepos->currentPage(),
                'per_page' => $paginatedRepos->perPage(),
                'total' => $paginatedRepos->total(),
                'last_page' => $paginatedRepos->lastPage(),
            ],
        ]);
    }

    /**
     * Hydrate PR groups with full models and limited runs.
     */
    private function hydratePrGroups(LengthAwarePaginator $paginatedGroups, Workspace $workspace): Collection
    {
        $groupData = collect($paginatedGroups->items());

        if ($groupData->isEmpty()) {
            return new Collection();
        }

        /** @var array<int, array{repository_id: int, pr_number: int}> $prKeys */
        $prKeys = $groupData->map(fn (stdClass $group): array => [
            'repository_id' => (int) $group->repository_id,
            'pr_number' => (int) $group->pr_number,
        ])->all();

        $latestRuns = $this->getLatestRunPerPr($prKeys, $workspace);
        $runsPerPr = $this->getRunsPerPr($prKeys, $workspace);

        /** @var array<int, int> $repositoryIds */
        $repositoryIds = $groupData->pluck('repository_id')->unique()->all();
        $repositories = Repository::query()
            ->whereIn('id', $repositoryIds)
            ->get(['id', 'name', 'full_name', 'private', 'language'])
            ->keyBy('id');

        return $groupData->map(function (stdClass $group) use ($latestRuns, $runsPerPr, $repositories): stdClass {
            $prKey = sprintf('%s:%s', $group->repository_id, $group->pr_number);
            /** @var Run|null $latestRun */
            $latestRun = $latestRuns->get($prKey);

            return (object) [
                'pull_request_number' => $group->pr_number,
                'pull_request_title' => $group->pr_title,
                'repository' => $repositories->get($group->repository_id),
                'runs_count' => $group->runs_count,
                'latest_run' => $latestRun,
                'latest_status' => $latestRun?->status->value,
                'runs' => $runsPerPr->get($prKey, collect()),
            ];
        })->filter(fn (stdClass $group): bool => $group->latest_run !== null);
    }

    /**
     * Get the latest run for each PR group.
     *
     * @param  array<int, array{repository_id: int, pr_number: int}>  $prKeys
     * @return Collection<string, Run>
     */
    private function getLatestRunPerPr(array $prKeys, Workspace $workspace): Collection
    {
        if ($prKeys === []) {
            return collect();
        }

        $effectivePrNumber = $this->effectivePrNumberExpression();
        $effectivePrNumberRuns = str_replace(['pr_number', 'metadata'], ['runs.pr_number', 'runs.metadata'], $effectivePrNumber);

        $latestCreatedAtSubquery = DB::table('runs')
            ->select([
                'repository_id',
                DB::raw($effectivePrNumber.' as effective_pr_number'),
                DB::raw('MAX(created_at) as max_created_at'),
            ])
            ->where('workspace_id', $workspace->id)
            ->where(function (QueryBuilder $q) use ($prKeys, $effectivePrNumber): void {
                foreach ($prKeys as $key) {
                    $q->orWhere(function (QueryBuilder $inner) use ($key, $effectivePrNumber): void {
                        $inner->where('repository_id', $key['repository_id'])
                            ->whereRaw($effectivePrNumber.' = ?', [$key['pr_number']]);
                    });
                }
            })
            ->groupBy('repository_id', DB::raw($effectivePrNumber));

        return Run::query()
            ->joinSub($latestCreatedAtSubquery, 'latest', function (JoinClause $join) use ($effectivePrNumberRuns): void {
                $join->on('runs.repository_id', '=', 'latest.repository_id')
                    ->whereRaw($effectivePrNumberRuns.' = latest.effective_pr_number')
                    ->on('runs.created_at', '=', 'latest.max_created_at');
            })
            ->where('runs.workspace_id', $workspace->id)
            ->with(['repository:id,name,full_name,private,language'])
            ->withCount('findings')
            ->get()
            ->keyBy(fn (Run $run): string => sprintf('%s:%s', $run->repository_id, $run->getEffectivePrNumber()));
    }

    /**
     * Get limited runs for each PR group.
     *
     * @param  array<int, array{repository_id: int, pr_number: int}>  $prKeys
     * @return Collection<string, Collection<int, Run>>
     */
    private function getRunsPerPr(array $prKeys, Workspace $workspace): Collection
    {
        if ($prKeys === []) {
            return collect();
        }

        $effectivePrNumber = $this->effectivePrNumberExpression();

        $allRuns = Run::query()
            ->where('workspace_id', $workspace->id)
            ->where(function (Builder $q) use ($prKeys, $effectivePrNumber): void {
                foreach ($prKeys as $key) {
                    $q->orWhere(function (Builder $inner) use ($key, $effectivePrNumber): void {
                        $inner->where('repository_id', $key['repository_id'])
                            ->whereRaw($effectivePrNumber.' = ?', [$key['pr_number']]);
                    });
                }
            })
            ->withCount('findings')
            ->orderByDesc('created_at')
            ->get();

        return $allRuns
            ->groupBy(fn (Run $run): string => sprintf('%s:%s', $run->repository_id, $run->getEffectivePrNumber()))
            ->map(fn (Collection $runs): Collection => $runs->take(self::MAX_RUNS_PER_GROUP)->values());
    }

    /**
     * Hydrate repository groups with PR subgroups.
     */
    private function hydrateRepositoryGroups(
        LengthAwarePaginator $paginatedRepos,
        ListWorkspaceRunsRequest $request,
        Workspace $workspace
    ): Collection {
        $repoData = collect($paginatedRepos->items());

        if ($repoData->isEmpty()) {
            return new Collection();
        }

        /** @var array<int, int> $repositoryIds */
        $repositoryIds = $repoData->pluck('repository_id')->all();
        $repositories = Repository::query()
            ->whereIn('id', $repositoryIds)
            ->get(['id', 'name', 'full_name', 'private', 'language'])
            ->keyBy('id');

        $prGroupsPerRepo = $this->getPrGroupsPerRepository($repositoryIds, $request, $workspace);

        return $repoData->map(function (stdClass $repoGroup) use ($repositories, $prGroupsPerRepo): stdClass {
            $repository = $repositories->get($repoGroup->repository_id);

            return (object) [
                'repository' => $repository,
                'pull_requests_count' => $repoGroup->pull_requests_count,
                'runs_count' => $repoGroup->runs_count,
                'pull_requests' => $prGroupsPerRepo->get($repoGroup->repository_id, collect()),
            ];
        })->filter(fn (stdClass $group): bool => $group->repository !== null);
    }

    /**
     * Get PR groups for each repository with limited runs.
     *
     * @param  array<int, int>  $repositoryIds
     */
    private function getPrGroupsPerRepository(
        array $repositoryIds,
        ListWorkspaceRunsRequest $request,
        Workspace $workspace
    ): Collection {
        if ($repositoryIds === []) {
            return collect();
        }

        $effectivePrNumber = $this->effectivePrNumberExpression();
        $effectivePrTitle = $this->effectivePrTitleExpression();

        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('repository_id', $repositoryIds)
            ->whereRaw($effectivePrNumber.' IS NOT NULL');

        $this->applyFilters($baseQuery, $request, $workspace);

        $prGroups = DB::table('runs')
            ->whereIn('id', $baseQuery->select('id'))
            ->select([
                'repository_id',
                DB::raw($effectivePrNumber.' as pr_number'),
                DB::raw('MAX('.$effectivePrTitle.') as pr_title'),
                DB::raw('COUNT(*) as runs_count'),
                DB::raw('MAX(created_at) as latest_created_at'),
            ])
            ->groupBy('repository_id', DB::raw($effectivePrNumber))
            ->orderByDesc('latest_created_at')
            ->get();

        /** @var array<int, array{repository_id: int, pr_number: int}> $prKeys */
        $prKeys = $prGroups->map(fn (stdClass $group): array => [
            'repository_id' => (int) $group->repository_id,
            'pr_number' => (int) $group->pr_number,
        ])->all();

        $latestRuns = $this->getLatestRunPerPr($prKeys, $workspace);
        $runsPerPr = $this->getRunsPerPr($prKeys, $workspace);

        $repositories = Repository::query()
            ->whereIn('id', $repositoryIds)
            ->get(['id', 'name', 'full_name', 'private', 'language'])
            ->keyBy('id');

        return $prGroups
            ->map(function (stdClass $group) use ($latestRuns, $runsPerPr, $repositories): stdClass {
                $prKey = sprintf('%s:%s', $group->repository_id, $group->pr_number);
                /** @var Run|null $latestRun */
                $latestRun = $latestRuns->get($prKey);

                return (object) [
                    'repository_id' => $group->repository_id,
                    'pull_request_number' => $group->pr_number,
                    'pull_request_title' => $group->pr_title,
                    'repository' => $repositories->get($group->repository_id),
                    'runs_count' => $group->runs_count,
                    'latest_run' => $latestRun,
                    'latest_status' => $latestRun?->status->value,
                    'runs' => $runsPerPr->get($prKey, collect()),
                ];
            })
            ->filter(fn (stdClass $group): bool => $group->latest_run !== null)
            ->groupBy('repository_id');
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

        if (isset($validated['from_date']) && is_string($validated['from_date'])) {
            $query->whereDate('created_at', '>=', $validated['from_date']);
        }

        if (isset($validated['to_date']) && is_string($validated['to_date'])) {
            $query->whereDate('created_at', '<=', $validated['to_date']);
        }

        if (isset($validated['search']) && is_string($validated['search'])) {
            $search = $validated['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('pr_title', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('metadata->pull_request_title', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('metadata->sender_login', 'like', sprintf('%%%s%%', $search))
                    ->orWhereHas('repository', function (Builder $repoQuery) use ($search): void {
                        $repoQuery->where('name', 'like', sprintf('%%%s%%', $search))
                            ->orWhere('full_name', 'like', sprintf('%%%s%%', $search));
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
