<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Actions\Runs\Resolvers\RunGroupPaginationResolver;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use stdClass;

/**
 * List workspace runs with filtering, sorting, and optional grouping.
 */
final readonly class ListWorkspaceRuns
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private ApplyRunFilters $runFilters = new ApplyRunFilters,
        private ?RunGroupPaginationResolver $runGroupPaginator = null,
        private ?HydrateRunGroups $runGroupHydrator = null,
    ) {}

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

        $this->runFilters->applyFilters($query, $filters, $workspace);
        $this->runFilters->applySorting($query, $sortBy, $sortOrder);

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
        $runGroupPaginator = $this->runGroupPaginator();

        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereRaw($runGroupPaginator->effectivePrNumber().' IS NOT NULL');

        $this->runFilters->applyFilters($baseQuery, $filters, $workspace);

        $paginatedGroups = $runGroupPaginator->paginatePullRequestGroups($baseQuery, $perPage);

        $groups = $this->hydrateRunGroups()->hydratePrGroups($paginatedGroups, $workspace);

        return [
            'groups' => $groups,
            'pagination' => $runGroupPaginator->pagination($paginatedGroups),
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
        $runGroupPaginator = $this->runGroupPaginator();
        $baseQuery = Run::query()->where('workspace_id', $workspace->id);

        $this->runFilters->applyFilters($baseQuery, $filters, $workspace);

        $paginatedRepos = $runGroupPaginator->paginateRepositoryGroups($baseQuery, $perPage);

        $groups = $this->hydrateRunGroups()->hydrateRepositoryGroups($paginatedRepos, $filters, $workspace);

        return [
            'groups' => $groups,
            'pagination' => $runGroupPaginator->pagination($paginatedRepos),
        ];
    }

    /**
     * Resolve the run group paginator action from the container.
     */
    private function runGroupPaginator(): RunGroupPaginationResolver
    {
        return $this->runGroupPaginator ?? app(RunGroupPaginationResolver::class);
    }

    /**
     * Resolve the run group hydration action from the container.
     */
    private function hydrateRunGroups(): HydrateRunGroups
    {
        return $this->runGroupHydrator ?? app(HydrateRunGroups::class);
    }
}
