<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\SentinelMessageService;
use App\Support\RepositoryNameParser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts an auto-review disabled comment to a PR.
 */
final readonly class PostAutoReviewDisabledComment implements PostsAutoReviewDisabledComment
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private SentinelMessageService $messageService
    ) {}

    /**
     * Post an auto-review disabled comment to a pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Repository $repository, int $pullRequestNumber): ?int
    {
        try {
            $repository->loadMissing('installation');
            $installation = $repository->installation;

            if ($installation === null) {
                Log::warning('Cannot post auto-review disabled comment: repository has no installation', [
                    'repository_id' => $repository->id,
                ]);

                return null;
            }

            $parsed = RepositoryNameParser::parse($repository->full_name);
            if ($parsed === null) {
                Log::warning('Invalid repository full_name format', [
                    'repository_id' => $repository->id,
                    'full_name' => $repository->full_name,
                ]);

                return null;
            }

            ['owner' => $owner, 'repo' => $repo] = $parsed;

            $comment = $this->messageService->buildAutoReviewDisabledComment();

            $response = $this->gitHubApiService->createPullRequestComment(
                installationId: $installation->installation_id,
                owner: $owner,
                repo: $repo,
                number: $pullRequestNumber,
                body: $comment
            );

            if (! isset($response['id'])) {
                Log::error('GitHub API response missing comment id', [
                    'repository' => $repository->full_name,
                    'pr_number' => $pullRequestNumber,
                ]);

                return null;
            }

            /** @var int $commentId */
            $commentId = $response['id'];

            Log::info('Posted auto-review disabled comment to PR', [
                'repository' => $repository->full_name,
                'pr_number' => $pullRequestNumber,
                'comment_id' => $commentId,
            ]);

            return $commentId;
        } catch (Throwable $throwable) {
            Log::error('Failed to post auto-review disabled comment', [
                'repository' => $repository->full_name,
                'pr_number' => $pullRequestNumber,
                'exception' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
