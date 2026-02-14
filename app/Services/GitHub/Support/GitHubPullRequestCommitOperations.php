<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubPullRequestCommitOperations
{
    /**
     * Create a new operations instance.
     */
    public function __construct(
        private GitHubApiRequestExecutor $requestExecutor,
        private GitHubApiPayloadFactory $payloadFactory,
        private GitHubApiResponseGuard $responseGuard,
    ) {}

    /**
     * @return array<string, mixed>
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
     * @return array<int, array<string, mixed>>
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
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $comments
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
