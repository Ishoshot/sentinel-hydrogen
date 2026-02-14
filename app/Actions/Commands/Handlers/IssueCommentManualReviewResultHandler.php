<?php

declare(strict_types=1);

namespace App\Actions\Commands\Handlers;

use App\Actions\Commands\PostIssueCommentMessage;

final readonly class IssueCommentManualReviewResultHandler
{
    /**
     * Create a new result handler instance.
     */
    public function __construct(private PostIssueCommentMessage $postIssueCommentMessage) {}

    /**
     * Handle webhook-facing side effects for a manual review trigger result.
     *
     * @param  array{success: bool, run: \App\Models\Run|null, message: string}  $result
     */
    public function handle(array $result, int $installationId, string $repositoryFullName, int $pullRequestNumber): void
    {
        if (! $result['success'] && $result['run'] === null) {
            $this->postIssueCommentMessage->postReviewError(
                installationId: $installationId,
                repositoryFullName: $repositoryFullName,
                pullRequestNumber: $pullRequestNumber,
                message: $result['message'],
            );
        }
    }
}
