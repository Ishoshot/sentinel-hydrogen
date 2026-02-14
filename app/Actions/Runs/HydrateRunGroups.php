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

final readonly class HydrateRunGroups
{
    private const int MAX_RUNS_PER_GROUP = 10;

    /**
     * Create a new action instance.
     */
    public function __construct(
        private ResolveRunQueryExpressions $resolveRunQueryExpressions,
        private ApplyRunFilters $applyRunFilters,
    ) {}

    /**
     * @return Collection<int, stdClass>
     */
    public function hydratePrGroups(LengthAwarePaginator $paginatedGroups, Workspace $workspace): Collection
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
     * @param  array<string, mixed>  $filters
     * @return Collection<int, stdClass>
     */
    public function hydrateRepositoryGroups(
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
     * @param  array<int, array{repository_id: int, pr_number: int}>  $prKeys
     * @return Collection<string, Run>
     */
    private function getLatestRunPerPr(array $prKeys, Workspace $workspace): Collection
    {
        if ($prKeys === []) {
            return collect();
        }

        $effectivePrNumber = $this->resolveRunQueryExpressions->effectivePrNumber();
        $effectivePrNumberRuns = str_replace(['pr_number', 'metadata'], ['runs.pr_number', 'runs.metadata'], $effectivePrNumber);

        $latestCreatedAtSubquery = DB::table('runs')
            ->select([
                'repository_id',
                DB::raw($effectivePrNumber.' as effective_pr_number'),
                DB::raw('MAX(created_at) as max_created_at'),
            ])
            ->where('workspace_id', $workspace->id)
            ->where(function (QueryBuilder $query) use ($prKeys, $effectivePrNumber): void {
                foreach ($prKeys as $key) {
                    $query->orWhere(function (QueryBuilder $inner) use ($key, $effectivePrNumber): void {
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
     * @param  array<int, array{repository_id: int, pr_number: int}>  $prKeys
     * @return Collection<string, Collection<int, Run>>
     */
    private function getRunsPerPr(array $prKeys, Workspace $workspace): Collection
    {
        if ($prKeys === []) {
            return collect();
        }

        $effectivePrNumber = $this->resolveRunQueryExpressions->effectivePrNumber();

        $allRuns = Run::query()
            ->where('workspace_id', $workspace->id)
            ->where(function (Builder $query) use ($prKeys, $effectivePrNumber): void {
                foreach ($prKeys as $key) {
                    $query->orWhere(function (Builder $inner) use ($key, $effectivePrNumber): void {
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

        $effectivePrNumber = $this->resolveRunQueryExpressions->effectivePrNumber();
        $effectivePrTitle = $this->resolveRunQueryExpressions->effectivePrTitle();

        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('repository_id', $repositoryIds)
            ->whereRaw($effectivePrNumber.' IS NOT NULL');

        $this->applyRunFilters->applyFilters($baseQuery, $filters, $workspace);

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
