<?php

declare(strict_types=1);

namespace App\Actions\Commands\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;

final readonly class IssueCommentMessagePublisher
{
    /**
     * Create a new issue comment publisher.
     */
    public function __construct(private GitHubApiServiceContract $githubApi) {}

    /**
     * Publish an issue comment to GitHub.
     */
    public function publish(
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body,
    ): void {
        $this->githubApi->createIssueComment(
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            number: $number,
            body: $body,
        );
    }
}
