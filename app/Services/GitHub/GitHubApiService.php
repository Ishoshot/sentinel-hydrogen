<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Models\Installation;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubApiService implements GitHubApiServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private GitHubManager $github,
        private GitHubAppServiceContract $appService,
        private GitHubRateLimiter $rateLimiter,
    ) {}

    /**
     * Get installation details from GitHub.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<string, mixed> The installation data
     */
    public function getInstallation(int $installationId): array
    {
        $this->authenticateWithJwt();

        /** @var array<string, mixed> $installation */
        $installation = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->apps()->getInstallation($installationId),
            sprintf('getInstallation(%d)', $installationId)
        );

        return $installation;
    }

    /**
     * Get repositories accessible to an installation.
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return array<int, array<string, mixed>> List of repositories
     */
    public function getInstallationRepositories(int $installationId): array
    {
        $this->authenticateWithInstallationToken($installationId);

        $repositories = [];
        $page = 1;
        $perPage = 100;

        do {
            /** @var array{repositories?: array<int, array<string, mixed>>} $response */
            $response = $this->rateLimiter->handle(
                fn (): array => $this->github->connection()->apps()->listRepositories($page),
                sprintf('listRepositories(installation=%d, page=%d)', $installationId, $page)
            );

            /** @var array<int, array<string, mixed>> $repos */
            $repos = $response['repositories'] ?? [];
            $repositories = array_merge($repositories, $repos);
            $page++;
        } while (count($repos) === $perPage);

        return $repositories;
    }

    /**
     * Get a specific repository.
     *
     * @return array<string, mixed> The repository data
     */
    public function getRepository(int $installationId, string $owner, string $repo): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $repository */
        $repository = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->repo()->show($owner, $repo),
            sprintf('getRepository(%s/%s)', $owner, $repo)
        );

        return $repository;
    }

    /**
     * Get pull request details.
     *
     * @return array<string, mixed> The pull request data
     */
    public function getPullRequest(int $installationId, string $owner, string $repo, int $number): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $pullRequest */
        $pullRequest = $this->rateLimiter->handle(
            fn (): mixed => $this->github->connection()->pullRequest()->show($owner, $repo, $number),
            sprintf('getPullRequest(%s/%s#%d)', $owner, $repo, $number)
        );

        return $pullRequest;
    }

    /**
     * Get pull request files.
     *
     * @return array<int, array<string, mixed>> List of changed files
     */
    public function getPullRequestFiles(int $installationId, string $owner, string $repo, int $number): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<int, array<string, mixed>> $files */
        $files = $this->rateLimiter->handle(
            fn (): mixed => $this->github->connection()->pullRequest()->files($owner, $repo, $number),
            sprintf('getPullRequestFiles(%s/%s#%d)', $owner, $repo, $number)
        );

        return $files;
    }

    /**
     * Get file contents from a repository.
     *
     * @return array<string, mixed>|string The file content or decoded content
     */
    public function getFileContents(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): array|string
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed>|string $contents */
        $contents = $this->rateLimiter->handle(
            fn (): array|string => $this->github->connection()->repo()->contents()->show($owner, $repo, $path, $ref),
            sprintf('getFileContents(%s/%s/%s)', $owner, $repo, $path)
        );

        return $contents;
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
        $this->authenticateWithInstallationToken($installationId);

        $params = [
            'body' => $body,
            'event' => $event,
        ];

        // commit_id is required when using line-based comments
        if ($commitId !== null) {
            $params['commit_id'] = $commitId;
        }

        if ($comments !== []) {
            $params['comments'] = $comments;
        }

        /** @var array<string, mixed> $review */
        $review = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->pullRequest()->reviews()->create($owner, $repo, $number, $params),
            sprintf('createPullRequestReview(%s/%s#%d)', $owner, $repo, $number)
        );

        return $review;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $comment */
        $comment = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->issue()->comments()->create($owner, $repo, $number, ['body' => $body]),
            sprintf('createPullRequestComment(%s/%s#%d)', $owner, $repo, $number)
        );

        return $comment;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $comment */
        $comment = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->issue()->comments()->update($owner, $repo, $commentId, ['body' => $body]),
            sprintf('updatePullRequestComment(%s/%s#%d)', $owner, $repo, $commentId)
        );

        return $comment;
    }

    /**
     * Get an issue from a repository.
     *
     * @return array<string, mixed> The issue data
     */
    public function getIssue(int $installationId, string $owner, string $repo, int $number): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $issue */
        $issue = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->issue()->show($owner, $repo, $number),
            sprintf('getIssue(%s/%s#%d)', $owner, $repo, $number)
        );

        return $issue;
    }

    /**
     * Get comments on an issue.
     *
     * @return array<int, array<string, mixed>> List of comments
     */
    public function getIssueComments(int $installationId, string $owner, string $repo, int $number): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<int, array<string, mixed>> $comments */
        $comments = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->issue()->comments()->all($owner, $repo, $number),
            sprintf('getIssueComments(%s/%s#%d)', $owner, $repo, $number)
        );

        return $comments;
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
        $this->authenticateWithInstallationToken($installation->installation_id);

        return $this->github;
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
        $this->authenticateWithInstallationToken($installationId);

        $params = [
            'name' => $name,
            'head_sha' => $headSha,
            'status' => $status,
        ];

        if ($status === 'completed' && $conclusion !== null) {
            $params['conclusion'] = $conclusion;
        }

        if ($summary !== null) {
            $params['output'] = [
                'title' => $name,
                'summary' => $summary,
            ];

            if ($annotations !== []) {
                $params['output']['annotations'] = $annotations;
            }
        }

        $checkRun = $this->rateLimiter->handle(
            function () use ($owner, $repo, $params): mixed {
                /** @var \Github\Api\Repo $repoApi */
                $repoApi = $this->github->connection()->api('repo');

                return $repoApi->checkRuns()->create($owner, $repo, $params);
            },
            sprintf('createCheckRun(%s/%s@%s)', $owner, $repo, mb_substr($headSha, 0, 7))
        );

        /** @var array<string, mixed> $checkRun */
        return $checkRun;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array{sha: string, url: string, tree: array<int, array{path: string, mode: string, type: string, sha: string, size?: int}>, truncated: bool} $tree */
        $tree = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->git()->trees()->show(
                $owner,
                $repo,
                $sha,
                $recursive
            ),
            sprintf('getRepositoryTree(%s/%s@%s)', $owner, $repo, mb_substr($sha, 0, 7))
        );

        return $tree;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $comment */
        $comment = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->issue()->comments()->create($owner, $repo, $number, ['body' => $body]),
            sprintf('createIssueComment(%s/%s#%d)', $owner, $repo, $number)
        );

        return $comment;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $comment */
        $comment = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->issue()->comments()->update($owner, $repo, $commentId, ['body' => $body]),
            sprintf('updateIssueComment(%s/%s#%d)', $owner, $repo, $commentId)
        );

        return $comment;
    }

    /**
     * Get a git reference (branch or tag).
     *
     * @return array<string, mixed> The reference data
     */
    public function getReference(int $installationId, string $owner, string $repo, string $ref): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $reference */
        $reference = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->git()->references()->show($owner, $repo, $ref),
            sprintf('getReference(%s/%s@%s)', $owner, $repo, $ref)
        );

        return $reference;
    }

    /**
     * Create a git reference (branch).
     *
     * @return array<string, mixed> The created reference data
     */
    public function createReference(int $installationId, string $owner, string $repo, string $ref, string $sha): array
    {
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $reference */
        $reference = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->git()->references()->create($owner, $repo, [
                'ref' => $ref,
                'sha' => $sha,
            ]),
            sprintf('createReference(%s/%s@%s)', $owner, $repo, $ref)
        );

        return $reference;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $result */
        $result = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->repo()->contents()->create(
                $owner,
                $repo,
                $path,
                $content,
                $message,
                $branch
            ),
            sprintf('createFile(%s/%s/%s)', $owner, $repo, $path)
        );

        return $result;
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
        $this->authenticateWithInstallationToken($installationId);

        /** @var array<string, mixed> $pullRequest */
        $pullRequest = $this->rateLimiter->handle(
            fn (): array => $this->github->connection()->pullRequest()->create($owner, $repo, [
                'title' => $title,
                'body' => $body,
                'head' => $head,
                'base' => $base,
            ]),
            sprintf('createPullRequest(%s/%s %s->%s)', $owner, $repo, $head, $base)
        );

        return $pullRequest;
    }

    /**
     * Authenticate with JWT (for app-level operations).
     */
    private function authenticateWithJwt(): void
    {
        $jwt = $this->appService->generateJwt();
        $this->github->connection()->authenticate($jwt, authMethod: 'jwt');
    }

    /**
     * Authenticate with an installation token (for repository-level operations).
     */
    private function authenticateWithInstallationToken(int $installationId): void
    {
        $token = $this->appService->getInstallationToken($installationId);
        $this->github->connection()->authenticate($token, authMethod: 'access_token_header');
    }
}
