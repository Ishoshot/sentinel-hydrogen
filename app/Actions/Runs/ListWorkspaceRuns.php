<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * List workspace runs with filtering, sorting, and optional grouping.
 */
final class ListWorkspaceRuns
{
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

        $this->applyRunFilters()->applyFilters($query, $filters, $workspace);
        $this->applyRunFilters()->applySorting($query, $sortBy, $sortOrder);

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
        $effectivePrNumber = $this->resolveRunQueryExpressions()->effectivePrNumber();
        $effectivePrTitle = $this->resolveRunQueryExpressions()->effectivePrTitle();

        $baseQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereRaw($effectivePrNumber.' IS NOT NULL');

        $this->applyRunFilters()->applyFilters($baseQuery, $filters, $workspace);

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

        $groups = $this->hydrateRunGroups()->hydratePrGroups($paginatedGroups, $workspace);

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
        $baseQuery = Run::query()->where('workspace_id', $workspace->id);

        $this->applyRunFilters()->applyFilters($baseQuery, $filters, $workspace);

        $effectivePrNumber = $this->resolveRunQueryExpressions()->effectivePrNumber();

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

        $groups = $this->hydrateRunGroups()->hydrateRepositoryGroups($paginatedRepos, $filters, $workspace);

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
     * Resolve the run filter helper from the container.
     */
    private function applyRunFilters(): ApplyRunFilters
    {
        return app(ApplyRunFilters::class);
    }

    /**
     * Resolve SQL expression helpers for run grouping queries.
     */
    private function resolveRunQueryExpressions(): ResolveRunQueryExpressions
    {
        return app(ResolveRunQueryExpressions::class);
    }

    /**
     * Resolve the run group hydration action from the container.
     */
    private function hydrateRunGroups(): HydrateRunGroups
    {
        return app(HydrateRunGroups::class);
    }
}
