<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Contracts;

use App\Models\Repository;

/**
 * Contract for posting greeting comments to pull requests.
 */
interface PostsGreetingComment
{
    /**
     * Post a greeting comment to a pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Repository $repository, int $pullRequestNumber): ?int;
}
