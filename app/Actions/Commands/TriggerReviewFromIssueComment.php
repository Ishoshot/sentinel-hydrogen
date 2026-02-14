<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Actions\Reviews\TriggerManualReview;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

final readonly class TriggerReviewFromIssueComment
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private TriggerManualReview $triggerManualReview,
        private PostIssueCommentMessage $postIssueCommentMessage,
    ) {}

    /**
     * Trigger a manual review from an issue_comment webhook.
     *
     * @param  array<string, mixed>  $context
     */
    public function handle(
        Repository $repository,
        int $pullRequestNumber,
        string $senderLogin,
        int $installationId,
        array $context
    ): void {
        Log::info('Triggering manual review from @sentinel review command', $context);

        $result = $this->triggerManualReview->handle(
            repository: $repository,
            prNumber: $pullRequestNumber,
            senderLogin: $senderLogin,
        );

        if (! $result['success'] && $result['run'] === null) {
            $this->postIssueCommentMessage->postReviewError(
                installationId: $installationId,
                repositoryFullName: $repository->full_name,
                pullRequestNumber: $pullRequestNumber,
                message: $result['message'],
            );
        }

        Log::info('Manual review trigger completed', array_merge($context, [
            'success' => $result['success'],
            'run_id' => $result['run']?->id,
            'message' => $result['message'],
        ]));
    }
}
