<?php

declare(strict_types=1);

namespace App\Services\GitHub\Clients;

use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubIssueCommentClient
{
    /**
     * Create a new operations instance.
     */
    public function __construct(
        private GitHubInstallationOperationClient $operationInvoker,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createPullRequestComment(
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body
    ): array {
        return $this->createComment(
            operationName: sprintf('createPullRequestComment(%s/%s#%d)', $owner, $repo, $number),
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            number: $number,
            body: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePullRequestComment(
        int $installationId,
        string $owner,
        string $repo,
        int $commentId,
        string $body
    ): array {
        return $this->updateComment(
            operationName: sprintf('updatePullRequestComment(%s/%s#%d)', $owner, $repo, $commentId),
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            commentId: $commentId,
            body: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getIssue(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->operationInvoker->map(
            $installationId,
            sprintf('getIssue(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->issue()->show($owner, $repo, $number),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIssueComments(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->operationInvoker->listOfMaps(
            $installationId,
            sprintf('getIssueComments(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->all($owner, $repo, $number),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPullRequestComments(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->getIssueComments($installationId, $owner, $repo, $number);
    }

    /**
     * @return array<string, mixed>
     */
    public function createIssueComment(
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body
    ): array {
        return $this->createComment(
            operationName: sprintf('createIssueComment(%s/%s#%d)', $owner, $repo, $number),
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            number: $number,
            body: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function updateIssueComment(
        int $installationId,
        string $owner,
        string $repo,
        int $commentId,
        string $body
    ): array {
        return $this->updateComment(
            operationName: sprintf('updateIssueComment(%s/%s#%d)', $owner, $repo, $commentId),
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            commentId: $commentId,
            body: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createComment(
        string $operationName,
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body
    ): array {
        return $this->operationInvoker->map(
            $installationId,
            $operationName,
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->create($owner, $repo, $number, ['body' => $body]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function updateComment(
        string $operationName,
        int $installationId,
        string $owner,
        string $repo,
        int $commentId,
        string $body
    ): array {
        return $this->operationInvoker->map(
            $installationId,
            $operationName,
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->update($owner, $repo, $commentId, ['body' => $body]),
        );
    }
}
