<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Resolvers;

final class GitHubConfigCompareUrlResolver
{
    /**
     * Build the compare URL for the prepared branch.
     */
    public function build(string $owner, string $repo, string $baseBranch, string $branchName): string
    {
        return sprintf(
            'https://github.com/%s/%s/compare/%s...%s?expand=1',
            $owner,
            $repo,
            $baseBranch,
            $branchName
        );
    }
}
