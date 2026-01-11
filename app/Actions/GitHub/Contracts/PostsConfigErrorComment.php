<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Contracts;

use App\Models\Repository;

/**
 * Contract for posting configuration error comments to pull requests.
 */
interface PostsConfigErrorComment
{
    /**
     * Post a configuration error comment to a pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Repository $repository, int $pullRequestNumber, string $error): ?int;
}
