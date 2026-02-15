<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Models\Installation;
use App\Services\GitHub\Clients\GitHubApiRequestClient;
use App\Services\GitHub\Clients\GitHubIssueCommentClient;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Clients\GitHubAppOperationClient;
use App\Services\GitHub\Clients\GitHubInstallationRepositoriesClient;
use App\Services\GitHub\Clients\GitHubPullRequestCommitClient;
use App\Services\GitHub\Clients\GitHubRepositoryContentClient;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubApiService implements GitHubApiServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private GitHubAppOperationClient $appOperationInvoker,
        private GitHubApiRequestClient $requestExecutor,
        private GitHubInstallationRepositoriesClient $repositoriesPaginator,
        private GitHubRepositoryContentClient $repositoryContentOperations,
        private GitHubPullRequestCommitClient $pullRequestCommitOperations,
        private GitHubIssueCommentClient $issueCommentOperations,
    ) {}

    /**
     * Get installation details from GitHub.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<string, mixed> The installation data
     */
    public function getInstallation(int $installationId): array
    {
        return $this->appOperationInvoker->map(
            sprintf('getInstallation(%d)', $installationId),
            fn (GitHubManager $github): array => $github->connection()->apps()->getInstallation($installationId),
        );
    }

    /**
     * Get repositories accessible to an installation.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<int, array<string, mixed>> List of repositories
     */
    public function getInstallationRepositories(int $installationId): array
    {
        return $this->repositoriesPaginator->resolve($installationId);
    }

    /**
     * Get a specific repository.
     *
     * @return array<string, mixed> The repository data
     */
    public function getRepository(int $installationId, string $owner, string $repo): array
    {
        return $this->repositoryContentOperations->getRepository($installationId, $owner, $repo);
    }

    /**
     * Get pull request details.
     *
     * @return array<string, mixed> The pull request data
     */
    public function getPullRequest(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->pullRequestCommitOperations->getPullRequest($installationId, $owner, $repo, $number);
    }

    /**
     * Get pull request files.
     *
     * @return array<int, array<string, mixed>> List of changed files
     */
    public function getPullRequestFiles(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->pullRequestCommitOperations->getPullRequestFiles($installationId, $owner, $repo, $number);
    }

    /**
     * Get file contents from a repository.
     *
     * @return array<string, mixed>|string The file content or decoded content
     */
    public function getFileContents(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): array|string
    {
        return $this->repositoryContentOperations->getFileContents($installationId, $owner, $repo, $path, $ref);
    }

    /**
     * Create a review on a pull request.
     *
     * @param  string  $body  The review body
     * @param  string  $event  The review action: APPROVE, REQUEST_CHANGES, COMMENT
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $comments  Inline comments
     * @param  string|null  $commitId  The SHA of the commit to review (required for line-based comments)
     * @return array<string, mixed> The review response
     */
    public function createPullRequestReview(
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body,
        string $event = 'COMMENT',
        array $comments = [],
        ?string $commitId = null
    ): array {
        return $this->pullRequestCommitOperations->createPullRequestReview(
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            number: $number,
            body: $body,
            event: $event,
            comments: $comments,
            commitId: $commitId,
        );
    }

    /**
     * Create a comment on a pull request.
     *
     * @return array<string, mixed> The comment response
     */
    public function createPullRequestComment(
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body
    ): array {
        return $this->issueCommentOperations->createPullRequestComment($installationId, $owner, $repo, $number, $body);
    }

    /**
     * Update an existing comment on a pull request.
     *
     * @return array<string, mixed> The updated comment response
     */
    public function updatePullRequestComment(
        int $installationId,
        string $owner,
        string $repo,
        int $commentId,
        string $body
    ): array {
        return $this->issueCommentOperations->updatePullRequestComment($installationId, $owner, $repo, $commentId, $body);
    }

    /**
     * Get an issue from a repository.
     *
     * @return array<string, mixed> The issue data
     */
    public function getIssue(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->issueCommentOperations->getIssue($installationId, $owner, $repo, $number);
    }

    /**
     * Get comments on an issue.
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getIssueComments(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->issueCommentOperations->getIssueComments($installationId, $owner, $repo, $number);
    }

    /**
     * Get comments on a pull request (issue-style comments, not review comments).
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getPullRequestComments(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->issueCommentOperations->getPullRequestComments($installationId, $owner, $repo, $number);
    }

    /**
     * Get an authenticated GitHub client for an installation.
     */
    public function getClientForInstallation(Installation $installation): GitHubManager
    {
        return $this->requestExecutor->getClientForInstallation($installation);
    }

    /**
     * Create a check run on a commit.
     *
     * @param  array<int, array{path: string, start_line: int, end_line: int, annotation_level: string, message: string}>  $annotations
     * @return array<string, mixed>
     */
    public function createCheckRun(
        int $installationId,
        string $owner,
        string $repo,
        string $name,
        string $headSha,
        string $status = 'completed',
        ?string $conclusion = null,
        ?string $summary = null,
        array $annotations = []
    ): array {
        return $this->pullRequestCommitOperations->createCheckRun(
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            name: $name,
            headSha: $headSha,
            status: $status,
            conclusion: $conclusion,
            summary: $summary,
            annotations: $annotations,
        );
    }

    /**
     * Get the repository tree (list of files) at a specific commit.
     *
     * @return array{sha: string, url: string, tree: array<int, array{path: string, mode: string, type: string, sha: string, size?: int}>, truncated: bool}
     */
    public function getRepositoryTree(
        int $installationId,
        string $owner,
        string $repo,
        string $sha,
        bool $recursive = false
    ): array {
        return $this->repositoryContentOperations->getRepositoryTree($installationId, $owner, $repo, $sha, $recursive);
    }

    /**
     * Create a comment on an issue.
     *
     * @return array<string, mixed> The comment response
     */
    public function createIssueComment(
        int $installationId,
        string $owner,
        string $repo,
        int $number,
        string $body
    ): array {
        return $this->issueCommentOperations->createIssueComment($installationId, $owner, $repo, $number, $body);
    }

    /**
     * Update an existing issue comment.
     *
     * @return array<string, mixed> The updated comment response
     */
    public function updateIssueComment(
        int $installationId,
        string $owner,
        string $repo,
        int $commentId,
        string $body
    ): array {
        return $this->issueCommentOperations->updateIssueComment($installationId, $owner, $repo, $commentId, $body);
    }

    /**
     * Get a git reference (branch or tag).
     *
     * @return array<string, mixed> The reference data
     */
    public function getReference(int $installationId, string $owner, string $repo, string $ref): array
    {
        return $this->repositoryContentOperations->getReference($installationId, $owner, $repo, $ref);
    }

    /**
     * Create a git reference (branch).
     *
     * @return array<string, mixed> The created reference data
     */
    public function createReference(int $installationId, string $owner, string $repo, string $ref, string $sha): array
    {
        return $this->repositoryContentOperations->createReference($installationId, $owner, $repo, $ref, $sha);
    }

    /**
     * Check if a file exists in a repository.
     */
    public function fileExists(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): bool
    {
        return $this->repositoryContentOperations->fileExists($installationId, $owner, $repo, $path, $ref);
    }

    /**
     * Create or update a file in a repository.
     *
     * @return array<string, mixed> The commit data
     */
    public function createFile(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        string $content,
        string $message,
        string $branch
    ): array {
        return $this->repositoryContentOperations->createFile(
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            path: $path,
            content: $content,
            message: $message,
            branch: $branch,
        );
    }

    /**
     * Create a pull request.
     *
     * @return array<string, mixed> The pull request data
     */
    public function createPullRequest(
        int $installationId,
        string $owner,
        string $repo,
        string $title,
        string $body,
        string $head,
        string $base
    ): array {
        return $this->pullRequestCommitOperations->createPullRequest(
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            title: $title,
            body: $body,
            head: $head,
            base: $base,
        );
    }
}
