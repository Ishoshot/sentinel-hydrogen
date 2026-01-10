<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Models\Repository;
use App\Services\GitHub\GitHubApiService;
use App\Services\SentinelMessageService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts an initial greeting comment to a PR to let developers know Sentinel is reviewing.
 */
final readonly class PostPullRequestGreeting implements PostsGreetingComment
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiService $gitHubApiService,
        private SentinelMessageService $messageService
    ) {}

    /**
     * Post a greeting comment to a pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Repository $repository, int $pullRequestNumber): ?int
    {
        try {
            $repository->loadMissing('installation');
            $installation = $repository->installation;

            if ($installation === null) {
                Log::warning('Cannot post greeting: repository has no installation', [
                    'repository_id' => $repository->id,
                ]);

                return null;
            }

            [$owner, $repo] = explode('/', $repository->full_name);

            $comment = $this->messageService->buildGreetingComment();

            $response = $this->gitHubApiService->createPullRequestComment(
                installationId: $installation->installation_id,
                owner: $owner,
                repo: $repo,
                number: $pullRequestNumber,
                body: $comment
            );

            /** @var int $commentId */
            $commentId = $response['id'];

            Log::info('Posted greeting comment to PR', [
                'repository' => $repository->full_name,
                'pr_number' => $pullRequestNumber,
                'comment_id' => $commentId,
            ]);

            return $commentId;
        } catch (Throwable $throwable) {
            Log::error('Failed to post greeting comment', [
                'repository' => $repository->full_name,
                'pr_number' => $pullRequestNumber,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
