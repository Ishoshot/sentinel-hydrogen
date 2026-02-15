<?php

declare(strict_types=1);

namespace App\Actions\Runs\Resolvers;

use App\Models\Repository;
use App\Models\Run;
use Illuminate\Support\Collection;
use stdClass;

final class PullRequestGroupResolver
{
    /**
     * @param  Collection<int, stdClass>  $groupData
     * @param  Collection<string, Run>  $latestRuns
     * @param  Collection<string, Collection<int, Run>>  $runsPerPr
     * @param  Collection<int, Repository>  $repositories
     * @return Collection<int, stdClass>
     */
    public function hydrate(
        Collection $groupData,
        Collection $latestRuns,
        Collection $runsPerPr,
        Collection $repositories,
        bool $includeRepositoryId = false,
    ): Collection {
        return $groupData
            ->map(function (stdClass $group) use ($latestRuns, $runsPerPr, $repositories, $includeRepositoryId): stdClass {
                $prKey = sprintf('%s:%s', $group->repository_id, $group->pr_number);
                /** @var Run|null $latestRun */
                $latestRun = $latestRuns->get($prKey);

                $hydratedGroup = [
                    'pull_request_number' => $group->pr_number,
                    'pull_request_title' => $group->pr_title,
                    'repository' => $repositories->get($group->repository_id),
                    'runs_count' => $group->runs_count,
                    'latest_run' => $latestRun,
                    'latest_status' => $latestRun?->status->value,
                    'runs' => $runsPerPr->get($prKey, collect()),
                ];

                if ($includeRepositoryId) {
                    $hydratedGroup['repository_id'] = $group->repository_id;
                }

                return (object) $hydratedGroup;
            })
            ->filter(fn (stdClass $group): bool => $group->latest_run !== null);
    }
}
