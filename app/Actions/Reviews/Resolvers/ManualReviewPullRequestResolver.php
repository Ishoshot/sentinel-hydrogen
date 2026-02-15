<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Resolvers;

use App\Actions\Reviews\ValueObjects\ManualReviewPullRequestFetchResult;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ManualReviewPullRequestResolver
{
    /**
     * Create a new pull request fetcher.
     */
    public function __construct(private GitHubApiServiceContract $githubApi) {}

    /**
     * Fetch pull request data for manual review.
     *
     * @param  array{repository_id: int, pr_number: int, sender: string}  $context
     */
    public function resolve(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        array $context,
    ): ManualReviewPullRequestFetchResult {
        try {
            $pullRequestData = $this->githubApi->getPullRequest(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                number: $pullRequestNumber,
            );

            return ManualReviewPullRequestFetchResult::success($pullRequestData);
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch PR data for manual review', array_merge($context, [
                'error' => $throwable->getMessage(),
            ]));

            return ManualReviewPullRequestFetchResult::failure('Unable to fetch pull request details from GitHub.');
        }
    }
}
