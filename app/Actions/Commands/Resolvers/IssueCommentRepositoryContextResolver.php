<?php

declare(strict_types=1);

namespace App\Actions\Commands\Resolvers;

use App\Support\RepositoryNameParser;
use Illuminate\Support\Facades\Log;

final class IssueCommentRepositoryContextResolver
{
    /**
     * Resolve owner/repository values from a full repository name.
     *
     * @return array{owner: string, repo: string}|null
     */
    public function resolve(string $repositoryFullName): ?array
    {
        $parsedRepository = RepositoryNameParser::parse($repositoryFullName);

        if ($parsedRepository === null) {
            Log::warning('Invalid repository full name format', [
                'repository' => $repositoryFullName,
            ]);

            return null;
        }

        return $parsedRepository;
    }
}
