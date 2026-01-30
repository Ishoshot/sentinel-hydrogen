<?php

declare(strict_types=1);

namespace App\Actions\Runs;

use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * List runs for a specific repository.
 */
final class ListRepositoryRuns
{
    /**
     * Get paginated runs for a repository.
     *
     * @return LengthAwarePaginator<int, Run>
     */
    public function handle(Workspace $workspace, Repository $repository, int $perPage = 20): LengthAwarePaginator
    {
        return Run::query()
            ->where('workspace_id', $workspace->id)
            ->where('repository_id', $repository->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
