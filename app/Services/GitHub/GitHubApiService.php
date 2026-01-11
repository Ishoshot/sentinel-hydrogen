<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Contracts\GitHub\GitHubApiServiceContract;
use App\Models\Installation;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubApiService implements GitHubApiServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private GitHubManager $github,
        private GitHubAppService $appService
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
        $installation = $this->github->connection()->apps()->getInstallation($installationId);

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
            $response = $this->github->connection()
                ->apps()
                ->listRepositories($page);

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
        $repository = $this->github->connection()->repo()->show($owner, $repo);

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
        $pullRequest = $this->github->connection()->pullRequest()->show($owner, $repo, $number);

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
        $files = $this->github->connection()->pullRequest()->files($owner, $repo, $number);

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
        $contents = $this->github->connection()->repo()->contents()->show($owner, $repo, $path, $ref);

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
        $review = $this->github->connection()->pullRequest()->reviews()->create($owner, $repo, $number, $params);

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
        $comment = $this->github->connection()->issue()->comments()->create($owner, $repo, $number, ['body' => $body]);

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
        $comment = $this->github->connection()->issue()->comments()->update($owner, $repo, $commentId, ['body' => $body]);

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
        $issue = $this->github->connection()->issue()->show($owner, $repo, $number);

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
        $comments = $this->github->connection()->issue()->comments()->all($owner, $repo, $number);

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
