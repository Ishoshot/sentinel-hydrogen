<?php

declare(strict_types=1);

namespace App\Actions\Runs\Support;

use App\Actions\Runs\ResolveRunQueryExpressions;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class LatestRunPerPullRequestLookup
{
    /**
     * Create a new lookup instance.
     */
    public function __construct(private ResolveRunQueryExpressions $resolveRunQueryExpressions) {}

    /**
     * @param  array<int, array{repository_id: int, pr_number: int}>  $prKeys
     * @return Collection<string, Run>
     */
    public function lookup(array $prKeys, Workspace $workspace): Collection
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
}
