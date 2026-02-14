<?php

declare(strict_types=1);

namespace App\Actions\Runs\Support;

use App\Models\Repository;
use Illuminate\Support\Collection;

final class RepositorySummaryLookup
{
    /**
     * @param  array<int, int>  $repositoryIds
     * @return Collection<int, Repository>
     */
    public function lookup(array $repositoryIds): Collection
    {
        if ($repositoryIds === []) {
            return collect();
        }

        return Repository::query()
            ->whereIn('id', $repositoryIds)
            ->get(['id', 'name', 'full_name', 'private', 'language'])
            ->keyBy('id');
    }
}
