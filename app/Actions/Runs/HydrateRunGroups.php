<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Actions\Runs\Support\LatestRunPerPullRequestLookup;
use App\Actions\Runs\Support\PullRequestGroupHydrator;
use App\Actions\Runs\Support\RepositoryGroupHydrator;
use App\Actions\Runs\Support\RepositorySummaryLookup;
use App\Actions\Runs\Support\RunsPerPullRequestLookup;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private LatestRunPerPullRequestLookup $latestRunPerPullRequestLookup,
        private RunsPerPullRequestLookup $runsPerPullRequestLookup,
        private RepositorySummaryLookup $repositorySummaryLookup,
        private PullRequestGroupHydrator $pullRequestGroupHydrator,
        private RepositoryGroupHydrator $repositoryGroupHydrator,
    ) {}

    /**
     * @return Collection<int, stdClass>
     */
    public function hydratePrGroups(LengthAwarePaginator $paginatedGroups, Workspace $workspace): Collection
    {
        /** @var Collection<int, stdClass> $groupData */
        $groupData = collect($paginatedGroups->items());

        if ($groupData->isEmpty()) {
            return new Collection();
        }

        $prKeys = $this->extractPrKeys($groupData);
        $latestRuns = $this->latestRunPerPullRequestLookup->lookup($prKeys, $workspace);
        $runsPerPr = $this->runsPerPullRequestLookup->lookup($prKeys, $workspace, self::MAX_RUNS_PER_GROUP);

        /** @var array<int, int> $repositoryIds */
        $repositoryIds = $groupData->pluck('repository_id')->unique()->all();
        $repositories = $this->repositorySummaryLookup->lookup($repositoryIds);

        return $this->pullRequestGroupHydrator->hydrate($groupData, $latestRuns, $runsPerPr, $repositories);
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
        /** @var Collection<int, stdClass> $repoData */
        $repoData = collect($paginatedRepos->items());

        if ($repoData->isEmpty()) {
            return new Collection();
        }

        /** @var array<int, int> $repositoryIds */
        $repositoryIds = $repoData->pluck('repository_id')->all();
        $repositories = $this->repositorySummaryLookup->lookup($repositoryIds);
        $prGroupsPerRepo = $this->getPrGroupsPerRepository($repositoryIds, $filters, $workspace);

        return $this->repositoryGroupHydrator->hydrate($repoData, $repositories, $prGroupsPerRepo);
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

        /** @var Collection<int, stdClass> $prGroups */
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

        $prKeys = $this->extractPrKeys($prGroups);
        $latestRuns = $this->latestRunPerPullRequestLookup->lookup($prKeys, $workspace);
        $runsPerPr = $this->runsPerPullRequestLookup->lookup($prKeys, $workspace, self::MAX_RUNS_PER_GROUP);
        $repositories = $this->repositorySummaryLookup->lookup($repositoryIds);

        return $this->pullRequestGroupHydrator
            ->hydrate($prGroups, $latestRuns, $runsPerPr, $repositories, includeRepositoryId: true)
            ->groupBy('repository_id');
    }

    /**
     * @param  Collection<int, stdClass>  $groups
     * @return array<int, array{repository_id: int, pr_number: int}>
     */
    private function extractPrKeys(Collection $groups): array
    {
        return $groups->map(fn (stdClass $group): array => [
            'repository_id' => (int) $group->repository_id,
            'pr_number' => (int) $group->pr_number,
        ])->all();
    }
}
