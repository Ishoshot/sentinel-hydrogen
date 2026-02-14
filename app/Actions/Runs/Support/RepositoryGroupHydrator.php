<?php

declare(strict_types=1);

namespace App\Actions\Runs\Support;

use App\Models\Repository;
use Illuminate\Support\Collection;
use stdClass;

final class RepositoryGroupHydrator
{
    /**
     * @param  Collection<int, stdClass>  $repositoryGroups
     * @param  Collection<int, Repository>  $repositories
     * @param  Collection<int, Collection<int, stdClass>>  $prGroupsPerRepository
     * @return Collection<int, stdClass>
     */
    public function hydrate(
        Collection $repositoryGroups,
        Collection $repositories,
        Collection $prGroupsPerRepository,
    ): Collection {
        return $repositoryGroups
            ->map(function (stdClass $repositoryGroup) use ($repositories, $prGroupsPerRepository): stdClass {
                $repository = $repositories->get($repositoryGroup->repository_id);

                return (object) [
                    'repository' => $repository,
                    'pull_requests_count' => $repositoryGroup->pull_requests_count,
                    'runs_count' => $repositoryGroup->runs_count,
                    'pull_requests' => $prGroupsPerRepository->get($repositoryGroup->repository_id, collect()),
                ];
            })
            ->filter(fn (stdClass $group): bool => $group->repository !== null);
    }
}
