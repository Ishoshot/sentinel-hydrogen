<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Contracts;

use App\Models\Repository;

/**
 * Contract for posting auto-review disabled comments to pull requests.
 */
interface PostsAutoReviewDisabledComment
{
    /**
     * Post an auto-review disabled comment to a pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Repository $repository, int $pullRequestNumber): ?int;
}
