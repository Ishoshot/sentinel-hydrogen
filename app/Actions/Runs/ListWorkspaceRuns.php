<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * List workspace runs with filtering, sorting, and optional grouping.
 */
final class ListWorkspaceRuns
{
    private const int MAX_RUNS_PER_GROUP = 10;

    /**
     * List runs with flat pagination (no grouping).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Run>
     */
    public function flat(
        Workspace $workspace,
        array $filters,
        string $sortBy,
        string $sortOrder,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Run::query()
            ->where('workspace_id', $workspace->id)
            ->with(['repository:id,name,full_name,private,language'])
            ->withCount('findings');

        $this->applyFilters($query, $filters, $workspace);
        $this->applySorting($query, $sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * List runs grouped by pull request.
     *
     * @param  array<string, mixed>  $filters
     * @return array{groups: Collection<int, stdClass>, pagination: array<string, int>}
     */
    public function groupedByPullRequest(
        Workspace $workspace,
        array $filters,
        int $perPage,
    ): array {
        $effectivePrNumber = $this->effectivePrNumberExpression();
        $effectivePrTitle = $this->effectivePrTitleExpression();

        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereRaw($effectivePrNumber.' IS NOT NULL');

        $this->applyFilters($baseQuery, $filters, $workspace);

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
            ->paginate($perPage);

        $groups = $this->hydratePrGroups($paginatedGroups, $workspace);

        return [
            'groups' => $groups,
            'pagination' => [
                'current_page' => $paginatedGroups->currentPage(),
                'per_page' => $paginatedGroups->perPage(),
                'total' => $paginatedGroups->total(),
                'last_page' => $paginatedGroups->lastPage(),
            ],
        ];
    }

    /**
     * List runs grouped by repository.
     *
     * @param  array<string, mixed>  $filters
     * @return array{groups: Collection<int, stdClass>, pagination: array<string, int>}
     */
    public function groupedByRepository(
        Workspace $workspace,
        array $filters,
        int $perPage,
    ): array {
        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id);

        $this->applyFilters($baseQuery, $filters, $workspace);

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
            ->paginate($perPage);

        $groups = $this->hydrateRepositoryGroups($paginatedRepos, $filters, $workspace);

        return [
            'groups' => $groups,
            'pagination' => [
                'current_page' => $paginatedRepos->currentPage(),
                'per_page' => $paginatedRepos->perPage(),
                'total' => $paginatedRepos->total(),
                'last_page' => $paginatedRepos->lastPage(),
            ],
        ];
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
     * Apply filters to the runs query.
     *
     * @param  Builder<Run>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, Workspace $workspace): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['repository_id'])) {
            $repository = Repository::query()
                ->where('id', $filters['repository_id'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($repository !== null) {
                $query->where('repository_id', $repository->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (isset($filters['risk_level'])) {
            $query->whereJsonContains('metadata->review_summary->risk_level', $filters['risk_level']);
        }

        if (isset($filters['author'])) {
            $query->where(function (Builder $q) use ($filters): void {
                $q->whereJsonContains('metadata->author->login', $filters['author'])
                    ->orWhere('metadata->sender_login', $filters['author']);
            });
        }

        if (isset($filters['from_date']) && is_string($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date']) && is_string($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['search']) && is_string($filters['search'])) {
            $search = $filters['search'];
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
    private function applySorting(Builder $query, string $sortBy, string $sortOrder): void
    {
        if ($sortBy === 'findings_count') {
            $query->orderBy('findings_count', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Hydrate PR groups with full models and limited runs.
     *
     * @return Collection<int, stdClass>
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
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, stdClass>
     */
    private function hydrateRepositoryGroups(
        LengthAwarePaginator $paginatedRepos,
        array $filters,
        Workspace $workspace,
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

        $prGroupsPerRepo = $this->getPrGroupsPerRepository($repositoryIds, $filters, $workspace);

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
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Collection<int, stdClass>>
     */
    private function getPrGroupsPerRepository(
        array $repositoryIds,
        array $filters,
        Workspace $workspace,
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

        $this->applyFilters($baseQuery, $filters, $workspace);

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
}
