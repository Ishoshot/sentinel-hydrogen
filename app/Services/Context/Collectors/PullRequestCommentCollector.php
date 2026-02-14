<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\PullRequestCommentNormalizer;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\RepositoryCoordinatesResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects PR discussion comments for review context.
 *
 * Fetches conversation/discussion comments on the pull request to provide
 * context about ongoing discussions and feedback.
 */
final readonly class PullRequestCommentCollector implements ContextCollector
{
    /**
     * Create a new PullRequestCommentCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private PullRequestCommentNormalizer $commentNormalizer = new PullRequestCommentNormalizer,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'pr_comments';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 70; // Medium-high priority - discussion provides useful context
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        $coordinates = $this->coordinatesResolver->resolve($repository);
        if (! $coordinates instanceof \App\Services\GitHub\ValueObjects\RepositoryCoordinates) {
            return;
        }

        $pullRequestNumber = $this->resolvePullRequestNumber($run);
        if ($pullRequestNumber <= 0) {
            return;
        }

        try {
            $rawComments = $this->gitHubApiService->getPullRequestComments(
                $coordinates->installationId,
                $coordinates->owner,
                $coordinates->repo,
                $pullRequestNumber
            );

            // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
            if (! is_array($rawComments)) {
                Log::warning('PullRequestCommentCollector: Unexpected response format from GitHub API', [
                    'pr_number' => $pullRequestNumber,
                ]);

                return;
            }

            $comments = $this->commentNormalizer->normalize($rawComments);
            $bag->prComments = $comments;

            Log::info('PullRequestCommentCollector: Collected PR comments', [
                'repository' => $coordinates->fullName,
                'pr_number' => $pullRequestNumber,
                'comments_count' => count($comments),
            ]);
        } catch (Throwable $throwable) {
            Log::warning('PullRequestCommentCollector: Failed to fetch PR comments', [
                'repository' => $coordinates->fullName,
                'pr_number' => $pullRequestNumber,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Resolve pull request number from run metadata.
     */
    private function resolvePullRequestNumber(Run $run): int
    {
        $metadata = $run->metadata ?? [];

        return is_int($metadata['pull_request_number'] ?? null)
            ? $metadata['pull_request_number']
            : 0;
    }
}
