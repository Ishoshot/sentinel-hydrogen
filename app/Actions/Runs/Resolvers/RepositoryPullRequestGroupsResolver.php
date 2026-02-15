<?php

declare(strict_types=1);

namespace App\Actions\Runs\Resolvers;

use App\Actions\Runs\ApplyRunFilters;
use App\Actions\Runs\ResolveRunQueryExpressions;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class RepositoryPullRequestGroupsResolver
{
    /**
     * Create a new lookup instance.
     */
    public function __construct(
        private ResolveRunQueryExpressions $resolveRunQueryExpressions,
        private ApplyRunFilters $applyRunFilters,
    ) {}

    /**
     * @param  array<int, int>  $repositoryIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, stdClass>
     */
    public function lookup(array $repositoryIds, array $filters, Workspace $workspace): Collection
    {
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

        /** @var Collection<int, stdClass> $groups */
        $groups = DB::table('runs')
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

        return $groups;
    }
}
