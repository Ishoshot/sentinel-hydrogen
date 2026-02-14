<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Support\RepositoryNameParser;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PostIssueCommentMessage
{
    /**
     * Create a new action instance.
     */
    public function __construct(private GitHubApiServiceContract $githubApi) {}

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
        $parsedRepository = RepositoryNameParser::parse($repositoryFullName);

        if ($parsedRepository === null) {
            Log::warning('Invalid repository full name format', [
                'repository' => $repositoryFullName,
            ]);

            return;
        }

        try {
            $this->githubApi->createIssueComment(
                installationId: $installationId,
                owner: $parsedRepository['owner'],
                repo: $parsedRepository['repo'],
                number: $number,
                body: '**Sentinel**: '.$message
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
