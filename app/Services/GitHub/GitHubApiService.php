<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Models\Installation;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\GitHubApiPayloadFactory;
use App\Services\GitHub\Support\GitHubApiRequestExecutor;
use App\Services\GitHub\Support\GitHubApiResponseGuard;
use App\Services\GitHub\Support\GitHubInstallationRepositoriesPaginator;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubApiService implements GitHubApiServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private GitHubApiRequestExecutor $requestExecutor,
        private GitHubInstallationRepositoriesPaginator $repositoriesPaginator,
        private GitHubApiPayloadFactory $payloadFactory,
        private GitHubApiResponseGuard $responseGuard,
    ) {}

    /**
     * Get installation details from GitHub.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<string, mixed> The installation data
     */
    public function getInstallation(int $installationId): array
    {
        return $this->responseGuard->map($this->requestExecutor->runApp(
            sprintf('getInstallation(%d)', $installationId),
            fn (GitHubManager $github): array => $github->connection()->apps()->getInstallation($installationId),
        ));
    }

    /**
     * Get repositories accessible to an installation.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<int, array<string, mixed>> List of repositories
     */
    public function getInstallationRepositories(int $installationId): array
    {
        return $this->repositoriesPaginator->fetch($installationId);
    }

    /**
     * Get a specific repository.
     *
     * @return array<string, mixed> The repository data
     */
    public function getRepository(int $installationId, string $owner, string $repo): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getRepository(%s/%s)', $owner, $repo),
            fn (GitHubManager $github): array => $github->connection()->repo()->show($owner, $repo),
        ));
    }

    /**
     * Get pull request details.
     *
     * @return array<string, mixed> The pull request data
     */
    public function getPullRequest(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getPullRequest(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): mixed => $github->connection()->pullRequest()->show($owner, $repo, $number),
        ));
    }

    /**
     * Get pull request files.
     *
     * @return array<int, array<string, mixed>> List of changed files
     */
    public function getPullRequestFiles(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->responseGuard->listOfMaps($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getPullRequestFiles(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): mixed => $github->connection()->pullRequest()->files($owner, $repo, $number),
        ));
    }

    /**
     * Get file contents from a repository.
     *
     * @return array<string, mixed>|string The file content or decoded content
     */
    public function getFileContents(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): array|string
    {
        return $this->responseGuard->mapOrString($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getFileContents(%s/%s/%s)', $owner, $repo, $path),
            fn (GitHubManager $github): array|string => $github->connection()->repo()->contents()->show($owner, $repo, $path, $ref),
        ));
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
        $params = $this->payloadFactory->pullRequestReview($body, $event, $comments, $commitId);

        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createPullRequestReview(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->pullRequest()->reviews()->create($owner, $repo, $number, $params),
        ));
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
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createPullRequestComment(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->create($owner, $repo, $number, ['body' => $body]),
        ));
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
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('updatePullRequestComment(%s/%s#%d)', $owner, $repo, $commentId),
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->update($owner, $repo, $commentId, ['body' => $body]),
        ));
    }

    /**
     * Get an issue from a repository.
     *
     * @return array<string, mixed> The issue data
     */
    public function getIssue(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getIssue(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->issue()->show($owner, $repo, $number),
        ));
    }

    /**
     * Get comments on an issue.
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getIssueComments(int $installationId, string $owner, string $repo, int $number): array
    {
        return $this->responseGuard->listOfMaps($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getIssueComments(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->all($owner, $repo, $number),
        ));
    }

    /**
     * Get comments on a pull request (issue-style comments, not review comments).
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getPullRequestComments(int $installationId, string $owner, string $repo, int $number): array
    {
        // PR comments are actually issue comments in GitHub's API
        return $this->getIssueComments($installationId, $owner, $repo, $number);
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
        $params = $this->payloadFactory->checkRun($name, $headSha, $status, $conclusion, $summary, $annotations);

        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createCheckRun(%s/%s@%s)', $owner, $repo, mb_substr($headSha, 0, 7)),
            function (GitHubManager $github) use ($owner, $repo, $params): mixed {
                /** @var \Github\Api\Repo $repoApi */
                $repoApi = $github->connection()->api('repo');

                return $repoApi->checkRuns()->create($owner, $repo, $params);
            }
        ));
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
        return $this->responseGuard->repositoryTree($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getRepositoryTree(%s/%s@%s)', $owner, $repo, mb_substr($sha, 0, 7)),
            fn (GitHubManager $github): array => $github->connection()->git()->trees()->show(
                $owner,
                $repo,
                $sha,
                $recursive
            ),
        ));
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
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createIssueComment(%s/%s#%d)', $owner, $repo, $number),
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->create($owner, $repo, $number, ['body' => $body]),
        ));
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
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('updateIssueComment(%s/%s#%d)', $owner, $repo, $commentId),
            fn (GitHubManager $github): array => $github->connection()->issue()->comments()->update($owner, $repo, $commentId, ['body' => $body]),
        ));
    }

    /**
     * Get a git reference (branch or tag).
     *
     * @return array<string, mixed> The reference data
     */
    public function getReference(int $installationId, string $owner, string $repo, string $ref): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getReference(%s/%s@%s)', $owner, $repo, $ref),
            fn (GitHubManager $github): array => $github->connection()->git()->references()->show($owner, $repo, $ref),
        ));
    }

    /**
     * Create a git reference (branch).
     *
     * @return array<string, mixed> The created reference data
     */
    public function createReference(int $installationId, string $owner, string $repo, string $ref, string $sha): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createReference(%s/%s@%s)', $owner, $repo, $ref),
            fn (GitHubManager $github): array => $github->connection()->git()->references()->create($owner, $repo, [
                'ref' => $ref,
                'sha' => $sha,
            ]),
        ));
    }

    /**
     * Check if a file exists in a repository.
     */
    public function fileExists(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): bool
    {
        try {
            $this->getFileContents($installationId, $owner, $repo, $path, $ref);

            return true;
        } catch (\Github\Exception\RuntimeException) {
            return false;
        }
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
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createFile(%s/%s/%s)', $owner, $repo, $path),
            fn (GitHubManager $github): array => $github->connection()->repo()->contents()->create(
                $owner,
                $repo,
                $path,
                $content,
                $message,
                $branch
            ),
        ));
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
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createPullRequest(%s/%s %s->%s)', $owner, $repo, $head, $base),
            fn (GitHubManager $github): array => $github->connection()->pullRequest()->create($owner, $repo, [
                'title' => $title,
                'body' => $body,
                'head' => $head,
                'base' => $base,
            ]),
        ));
    }
}
