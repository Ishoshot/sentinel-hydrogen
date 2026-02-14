<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ManualReviewAcknowledgmentCommentPoster
{
    /**
     * Create a new acknowledgment comment poster.
     */
    public function __construct(private GitHubApiServiceContract $githubApi) {}

    /**
     * Post the manual-review acknowledgment comment to the PR.
     */
    public function post(int $installationId, string $owner, string $repo, int $pullRequestNumber): ?int
    {
        try {
            $comment = $this->githubApi->createIssueComment(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                number: $pullRequestNumber,
                body: $this->message(),
            );

            return (int) ($comment['id'] ?? 0) ?: null;
        } catch (Throwable $throwable) {
            Log::warning('Failed to post acknowledgment comment', [
                'owner' => $owner,
                'repo' => $repo,
                'pr_number' => $pullRequestNumber,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the acknowledgment message for manual review.
     */
    private function message(): string
    {
        return "**Sentinel**: Starting code review...\n\nI'll analyze the changes in this pull request and post my findings shortly.";
    }
}
