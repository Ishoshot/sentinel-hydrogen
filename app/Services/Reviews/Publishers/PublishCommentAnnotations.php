<?php

declare(strict_types=1);

namespace App\Services\Reviews\Publishers;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;

final readonly class PublishCommentAnnotations
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    public function publish(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $reviewBody,
        array $inlineComments,
        bool $grouped,
    ): array {
        $response = $this->gitHubApiService->createPullRequestComment(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $reviewBody
        );

        if ($grouped) {
            $findingsBody = "## Detailed Findings\n\n";
            foreach ($inlineComments as $comment) {
                $findingsBody .= sprintf("### `%s` (line %d)\n\n%s\n\n---\n\n", $comment['path'], $comment['line'], $comment['body']);
            }

            $this->gitHubApiService->createPullRequestComment(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $findingsBody
            );

            return $response;
        }

        foreach ($inlineComments as $comment) {
            $commentBody = sprintf("**File:** `%s` (line %d)\n\n%s", $comment['path'], $comment['line'], $comment['body']);
            $this->gitHubApiService->createPullRequestComment(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $commentBody
            );
        }

        return $response;
    }

    /**
     * Post only the summary body when inline comments are unavailable.
     */
    public function postSummaryOnly(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $summary,
    ): void {
        $this->gitHubApiService->createPullRequestComment(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $summary
        );
    }
}
