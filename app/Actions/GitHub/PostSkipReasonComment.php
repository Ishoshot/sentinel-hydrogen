<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Contracts\GitHub\GitHubApiServiceContract;
use App\Enums\SkipReason;
use App\Models\Run;
use App\Services\SentinelMessageService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts a comment to GitHub explaining why a review was skipped or failed.
 */
final readonly class PostSkipReasonComment
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private SentinelMessageService $messageService
    ) {}

    /**
     * Post a skip/failure reason comment to the pull request.
     *
     * @return int|null The comment ID if successful, null otherwise
     */
    public function handle(Run $run, SkipReason $reason, ?string $errorType = null): ?int
    {
        try {
            $run->loadMissing(['repository.installation']);
            $repository = $run->repository;

            if ($repository === null) {
                Log::warning('Cannot post skip reason: run has no repository', [
                    'run_id' => $run->id,
                ]);

                return null;
            }

            $installation = $repository->installation;

            if ($installation === null) {
                Log::warning('Cannot post skip reason: repository has no installation', [
                    'repository_id' => $repository->id,
                ]);

                return null;
            }

            $metadata = $run->metadata ?? [];
            $pullRequestNumber = $metadata['pull_request_number'] ?? null;

            if (! is_int($pullRequestNumber)) {
                Log::warning('Cannot post skip reason: run has no pull request number', [
                    'run_id' => $run->id,
                ]);

                return null;
            }

            [$owner, $repo] = explode('/', (string) $repository->full_name);

            $comment = $this->buildComment($reason, $errorType);

            $response = $this->gitHubApiService->createPullRequestComment(
                installationId: $installation->installation_id,
                owner: $owner,
                repo: $repo,
                number: $pullRequestNumber,
                body: $comment
            );

            /** @var int $commentId */
            $commentId = $response['id'];

            Log::info('Posted skip reason comment to PR', [
                'run_id' => $run->id,
                'repository' => $repository->full_name,
                'pr_number' => $pullRequestNumber,
                'comment_id' => $commentId,
                'reason' => $reason->value,
            ]);

            return $commentId;
        } catch (Throwable $throwable) {
            Log::error('Failed to post skip reason comment', [
                'run_id' => $run->id,
                'reason' => $reason->value,
                'exception' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the appropriate comment based on the skip reason.
     */
    private function buildComment(SkipReason $reason, ?string $errorType): string
    {
        return match ($reason) {
            SkipReason::NoProviderKeys => $this->messageService->buildNoProviderKeysComment(),
            SkipReason::RunFailed => $this->messageService->buildRunFailedComment($errorType ?? 'Unknown'),
        };
    }
}
