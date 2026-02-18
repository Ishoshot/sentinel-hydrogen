<?php

declare(strict_types=1);

namespace App\Actions\CodeIndexing;

use App\Models\CodeIndex;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

final readonly class HandlePullRequestIndexCleanup
{
    /**
     * Remove pull-request-scoped code indexes for a repository PR pair.
     */
    public function handle(Repository $repository, int $pullRequestNumber): int
    {
        $deleted = CodeIndex::query()
            ->forRepository($repository)
            ->forPullRequest($pullRequestNumber)
            ->delete();

        Log::info('Pull request pre-index cleaned up', [
            'repository_id' => $repository->id,
            'pr_number' => $pullRequestNumber,
            'deleted_rows' => $deleted,
        ]);

        return (int) $deleted;
    }
}
