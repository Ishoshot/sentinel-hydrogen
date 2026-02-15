<?php

declare(strict_types=1);

namespace App\Actions\Commands\Handlers;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;

final readonly class IssueCommentMessageHandler
{
    /**
     * Create a new issue comment handler.
     */
    public function __construct(private GitHubApiServiceContract $githubApi) {}

    /**
     * Handle publishing an issue comment to GitHub.
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
