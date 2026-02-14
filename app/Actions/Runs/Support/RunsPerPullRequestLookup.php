<?php

declare(strict_types=1);

namespace App\Actions\Runs\Support;

use App\Actions\Runs\ResolveRunQueryExpressions;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class RunsPerPullRequestLookup
{
    /**
     * Create a new lookup instance.
     */
    public function __construct(private ResolveRunQueryExpressions $resolveRunQueryExpressions) {}

    /**
     * @param  array<int, array{repository_id: int, pr_number: int}>  $prKeys
     * @return Collection<string, Collection<int, Run>>
     */
    public function lookup(array $prKeys, Workspace $workspace, int $maxRunsPerGroup): Collection
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
            ->map(fn (Collection $runs): Collection => $runs->take($maxRunsPerGroup)->values());
    }
}
