<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Actions\Runs\Resolvers\LatestRunPerPullRequestResolver;
use App\Actions\Runs\Resolvers\PullRequestGroupResolver;
use App\Actions\Runs\Resolvers\RepositoryGroupResolver;
use App\Actions\Runs\Resolvers\RepositoryPullRequestGroupsResolver;
use App\Actions\Runs\Resolvers\RepositorySummaryResolver;
use App\Actions\Runs\Resolvers\RunsPerPullRequestResolver;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use stdClass;

final readonly class HydrateRunGroups
{
    private const int MAX_RUNS_PER_GROUP = 10;

    /**
     * Create a new action instance.
     */
    public function __construct(
        private RepositoryPullRequestGroupsResolver $repositoryPullRequestGroupsLookup,
        private LatestRunPerPullRequestResolver $latestRunPerPullRequestLookup,
        private RunsPerPullRequestResolver $runsPerPullRequestLookup,
        private RepositorySummaryResolver $repositorySummaryLookup,
        private PullRequestGroupResolver $pullRequestGroupHydrator,
        private RepositoryGroupResolver $repositoryGroupHydrator,
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
        $prGroups = $this->repositoryPullRequestGroupsLookup->lookup($repositoryIds, $filters, $workspace);
        $prKeys = $this->extractPrKeys($prGroups);
        $latestRuns = $this->latestRunPerPullRequestLookup->lookup($prKeys, $workspace);
        $runsPerPr = $this->runsPerPullRequestLookup->lookup($prKeys, $workspace, self::MAX_RUNS_PER_GROUP);
        $prGroupsPerRepo = $this->pullRequestGroupHydrator
            ->hydrate($prGroups, $latestRuns, $runsPerPr, $repositories, includeRepositoryId: true)
            ->groupBy('repository_id');

        return $this->repositoryGroupHydrator->hydrate($repoData, $repositories, $prGroupsPerRepo);
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
