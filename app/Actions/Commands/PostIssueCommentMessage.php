<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Actions\Commands\Publishers\IssueCommentMessagePublisher;
use App\Actions\Commands\Resolvers\IssueCommentRepositoryContextResolver;
use App\Actions\Commands\Support\IssueCommentBodyFormatter;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PostIssueCommentMessage
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private IssueCommentRepositoryContextResolver $repositoryContextResolver,
        private IssueCommentBodyFormatter $bodyFormatter,
        private IssueCommentMessagePublisher $messagePublisher,
    ) {}

    /**
     * Post a review error comment to a pull request thread.
     */
    public function postReviewError(
        int $installationId,
        string $repositoryFullName,
        int $pullRequestNumber,
        string $message
    ): void {
        $this->postComment(
            installationId: $installationId,
            repositoryFullName: $repositoryFullName,
            number: $pullRequestNumber,
            message: $message,
            failureLogMessage: 'Failed to post review error comment',
            numberContextKey: 'pr_number',
        );
    }

    /**
     * Post a command permission denied comment to an issue or PR thread.
     */
    public function postPermissionDenied(
        int $installationId,
        string $repositoryFullName,
        int $issueNumber,
        string $message
    ): void {
        $this->postComment(
            installationId: $installationId,
            repositoryFullName: $repositoryFullName,
            number: $issueNumber,
            message: $message,
            failureLogMessage: 'Failed to post permission denied comment',
            numberContextKey: 'issue_number',
        );
    }

    /**
     * Post a Sentinel-branded issue comment to GitHub.
     */
    private function postComment(
        int $installationId,
        string $repositoryFullName,
        int $number,
        string $message,
        string $failureLogMessage,
        string $numberContextKey
    ): void {
        $parsedRepository = $this->repositoryContextResolver->resolve($repositoryFullName);
        if ($parsedRepository === null) {
            return;
        }

        try {
            $this->messagePublisher->publish(
                installationId: $installationId,
                owner: $parsedRepository['owner'],
                repo: $parsedRepository['repo'],
                number: $number,
                body: $this->bodyFormatter->format($message),
            );
        } catch (Throwable $throwable) {
            Log::warning($failureLogMessage, [
                'repository' => $repositoryFullName,
                $numberContextKey => $number,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
