<?php

declare(strict_types=1);

namespace App\Services\GitHub\Contracts;

use App\Models\Installation;
use GrahamCampbell\GitHub\GitHubManager;

interface GitHubApiServiceContract
{
    /**
     * Get installation details from GitHub.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<string, mixed> The installation data
     */
    public function getInstallation(int $installationId): array;

    /**
     * Get repositories accessible to an installation.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<int, array<string, mixed>> List of repositories
     */
    public function getInstallationRepositories(int $installationId): array;

    /**
     * Get a specific repository.
     *
     * @return array<string, mixed> The repository data
     */
    public function getRepository(int $installationId, string $owner, string $repo): array;

    /**
     * Get pull request details.
     *
     * @return array<string, mixed> The pull request data
     */
    public function getPullRequest(int $installationId, string $owner, string $repo, int $number): array;

    /**
     * Get pull request files.
     *
     * @return array<int, array<string, mixed>> List of changed files
     */
    public function getPullRequestFiles(int $installationId, string $owner, string $repo, int $number): array;

    /**
     * Get file contents from a repository.
     *
     * @return array<string, mixed>|string The file content or decoded content
     */
    public function getFileContents(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): array|string;

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
    ): array;

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
    ): array;

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
    ): array;

    /**
     * Get an issue from a repository.
     *
     * @return array<string, mixed> The issue data
     */
    public function getIssue(int $installationId, string $owner, string $repo, int $number): array;

    /**
     * Get comments on an issue.
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getIssueComments(int $installationId, string $owner, string $repo, int $number): array;

    /**
     * Get comments on a pull request (issue-style comments, not review comments).
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getPullRequestComments(int $installationId, string $owner, string $repo, int $number): array;

    /**
     * Get an authenticated GitHub client for an installation.
     */
    public function getClientForInstallation(Installation $installation): GitHubManager;

    /**
     * Create a check run on a commit.
     *
     * @param  string  $name  The name of the check run
     * @param  string  $headSha  The SHA of the commit to check
     * @param  string  $status  The status: queued, in_progress, completed
     * @param  string|null  $conclusion  The conclusion: success, failure, neutral, cancelled, timed_out, action_required, skipped
     * @param  string|null  $summary  A summary of the check run
     * @param  array<int, array{path: string, start_line: int, end_line: int, annotation_level: string, message: string}>  $annotations
     * @return array<string, mixed> The check run response
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
    ): array;
}
