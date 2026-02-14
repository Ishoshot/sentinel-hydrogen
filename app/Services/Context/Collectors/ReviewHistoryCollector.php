<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\ReviewHistoryEntryBuilder;
use App\Services\Context\Collectors\Support\ReviewHistoryRunFetcher;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use Illuminate\Support\Facades\Log;

/**
 * Collects previous review history for the same PR.
 *
 * Fetches previous Sentinel reviews on the same pull request to provide
 * context about ongoing issues and feedback from earlier reviews.
 */
final readonly class ReviewHistoryCollector implements ContextCollector
{
    /**
     * Maximum number of previous reviews to include.
     */
    private const int MAX_REVIEWS = 3;

    /**
     * Maximum number of findings to include per review.
     */
    private const int MAX_FINDINGS_PER_REVIEW = 5;

    /**
     * Create a new instance.
     */
    public function __construct(
        private ReviewHistoryRunFetcher $runFetcher = new ReviewHistoryRunFetcher,
        private ReviewHistoryEntryBuilder $entryBuilder = new ReviewHistoryEntryBuilder,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'review_history';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 60; // Medium priority - useful context for follow-up reviews
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        if (! isset($params['repository'], $params['run'])) {
            return false;
        }

        if (! $params['repository'] instanceof Repository || ! $params['run'] instanceof Run) {
            return false;
        }

        // Only collect if we have a PR number to match against
        $metadata = $params['run']->metadata ?? [];

        return isset($metadata['pull_request_number'])
            && is_int($metadata['pull_request_number'])
            && $metadata['pull_request_number'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $currentRun */
        $currentRun = $params['run'];

        $metadata = $currentRun->metadata ?? [];
        $prNumber = $metadata['pull_request_number'] ?? 0;

        if (! is_int($prNumber) || $prNumber <= 0) {
            return;
        }

        // Find previous completed runs for the same PR
        $previousRuns = $this->runFetcher->fetch(
            repository: $repository,
            currentRun: $currentRun,
            prNumber: $prNumber,
            maxReviews: self::MAX_REVIEWS,
            maxFindingsPerReview: self::MAX_FINDINGS_PER_REVIEW,
            completedStatus: RunStatus::Completed,
        );

        if ($previousRuns->isEmpty()) {
            Log::debug('ReviewHistoryCollector: No previous reviews found for PR', [
                'repository_id' => $repository->id,
                'pr_number' => $prNumber,
            ]);

            return;
        }

        $reviewHistory = [];

        foreach ($previousRuns as $run) {
            $reviewHistory[] = $this->entryBuilder->build($run, self::MAX_FINDINGS_PER_REVIEW);
        }

        $bag->reviewHistory = $reviewHistory;

        Log::info('ReviewHistoryCollector: Collected review history', [
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
            'previous_reviews' => count($reviewHistory),
        ]);
    }
}
