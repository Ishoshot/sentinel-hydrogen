<?php

declare(strict_types=1);

namespace App\Services\Reviews\Publishers;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;

final readonly class PublishReviewAnnotations
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    public function publish(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $reviewBody,
        array $inlineComments,
        ?string $commitId,
        bool $grouped,
    ): array {
        if ($grouped) {
            return $this->gitHubApiService->createPullRequestReview(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $reviewBody,
                'COMMENT',
                $inlineComments,
                $commitId
            );
        }

        /** @var array<string, mixed> $response */
        $response = $this->gitHubApiService->createPullRequestReview(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $reviewBody,
            'COMMENT',
            [],
            $commitId
        );

        foreach ($inlineComments as $comment) {
            $this->gitHubApiService->createPullRequestReview(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                '',
                'COMMENT',
                [$comment],
                $commitId
            );
        }

        return $response;
    }

    /**
     * Post only a summary review comment without inline annotations.
     */
    public function postSummaryOnly(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $summary,
    ): void {
        $this->gitHubApiService->createPullRequestReview(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $summary,
            'COMMENT',
            []
        );
    }
}
