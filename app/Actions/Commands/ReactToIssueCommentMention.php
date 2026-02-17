<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Actions\Commands\Resolvers\IssueCommentRepositoryContextResolver;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Adds an immediate eyes reaction to a detected @sentinel mention comment.
 */
final readonly class ReactToIssueCommentMention
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private IssueCommentRepositoryContextResolver $repositoryContextResolver,
        private GitHubApiServiceContract $githubApi,
    ) {}

    /**
     * React to an issue comment with 👀.
     */
    public function handle(int $installationId, string $repositoryFullName, int $commentId): void
    {
        if ($commentId <= 0) {
            return;
        }

        $repository = $this->repositoryContextResolver->resolve($repositoryFullName);
        if ($repository === null) {
            return;
        }

        try {
            $this->githubApi->createIssueCommentReaction(
                installationId: $installationId,
                owner: $repository['owner'],
                repo: $repository['repo'],
                commentId: $commentId,
                content: 'eyes',
            );
        } catch (Throwable $throwable) {
            Log::debug('Failed to add eyes reaction for issue comment mention', [
                'repository' => $repositoryFullName,
                'comment_id' => $commentId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
