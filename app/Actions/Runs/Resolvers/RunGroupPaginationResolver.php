<?php

declare(strict_types=1);

namespace App\Actions\Runs\Resolvers;

use App\Actions\Runs\ResolveRunQueryExpressions;
use App\Models\Run;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class RunGroupPaginationResolver
{
    /**
     * Create a new paginator instance.
     */
    public function __construct(private ResolveRunQueryExpressions $resolveRunQueryExpressions) {}

    /**
     * Resolve SQL expression for effective pull request number.
     */
    public function effectivePrNumber(): string
    {
        return $this->resolveRunQueryExpressions->effectivePrNumber();
    }

    /**
     * @param  Builder<Run>  $baseQuery
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function paginatePullRequestGroups(Builder $baseQuery, int $perPage): LengthAwarePaginator
    {
        $effectivePrNumber = $this->resolveRunQueryExpressions->effectivePrNumber();
        $effectivePrTitle = $this->resolveRunQueryExpressions->effectivePrTitle();

        return DB::table('runs')
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
    }

    /**
     * @param  Builder<Run>  $baseQuery
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function paginateRepositoryGroups(Builder $baseQuery, int $perPage): LengthAwarePaginator
    {
        $effectivePrNumber = $this->resolveRunQueryExpressions->effectivePrNumber();

        return DB::table('runs')
            ->whereIn('id', $baseQuery->select('id'))
            ->select([
                'repository_id',
                DB::raw('COUNT(*) as runs_count'),
                DB::raw('COUNT(DISTINCT '.$effectivePrNumber.') as pull_requests_count'),
            ])
            ->groupBy('repository_id')
            ->orderByDesc('runs_count')
            ->paginate($perPage);
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
