<?php

declare(strict_types=1);

namespace App\Actions\Commands\Resolvers;

use App\Models\CommandRun;

/**
 * Resolves the GitHub posting context (installation, owner, repo, issue) from a command run.
 */
final readonly class PostingContextResolver
{
    /**
     * Resolve the context needed to post a comment.
     *
     * @return array{installation_id: int, owner: string, repo: string, issue_number: int}|null
     */
    public function resolve(CommandRun $commandRun): ?array
    {
        $repository = $commandRun->repository;
        $issueNumber = $commandRun->issue_number;

        if ($repository === null || $issueNumber === null) {
            return null;
        }

        $installation = $repository->installation;
        if ($installation === null) {
            return null;
        }

        return [
            'installation_id' => $installation->installation_id,
            'owner' => $repository->owner,
            'repo' => $repository->name,
            'issue_number' => $issueNumber,
        ];
    }
}
