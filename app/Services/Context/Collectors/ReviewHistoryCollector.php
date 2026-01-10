<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Enums\RunStatus;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
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
        $maxFindingsPerReview = self::MAX_FINDINGS_PER_REVIEW;

        /** @var \Illuminate\Database\Eloquent\Collection<int, Run> $previousRuns */
        $previousRuns = Run::query()
            ->where('repository_id', $repository->id)
            ->where('id', '!=', $currentRun->id)
            ->where('status', RunStatus::Completed)
            ->whereJsonContains('metadata->pull_request_number', $prNumber)
            ->with(['findings' => static function (\Illuminate\Database\Eloquent\Relations\Relation $query) use ($maxFindingsPerReview): void {
                $query->orderBy('severity')->limit($maxFindingsPerReview);
            }])
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_REVIEWS)
            ->get();

        if ($previousRuns->isEmpty()) {
            Log::debug('ReviewHistoryCollector: No previous reviews found for PR', [
                'repository_id' => $repository->id,
                'pr_number' => $prNumber,
            ]);

            return;
        }

        $reviewHistory = [];

        foreach ($previousRuns as $run) {
            /** @var Run $run */
            $findings = $run->findings;
            $findingsCount = $findings->count();

            // Build summary of findings by severity
            /** @var array<string, int> $severityCounts */
            $severityCounts = $findings->groupBy('severity')
                ->map(fn (\Illuminate\Support\Collection $group): int => $group->count())
                ->toArray();

            $summary = $this->buildSummary($severityCounts, $findingsCount);

            // Extract key findings
            $keyFindings = $findings->take(self::MAX_FINDINGS_PER_REVIEW)
                ->map(fn (Finding $finding): array => [
                    'severity' => $finding->severity,
                    'category' => $finding->category,
                    'title' => $finding->title,
                    'file_path' => $finding->file_path,
                ])
                ->toArray();

            $reviewHistory[] = [
                'run_id' => $run->id,
                'summary' => $summary,
                'findings_count' => $findingsCount,
                'severity_breakdown' => $severityCounts,
                'key_findings' => $keyFindings,
                'created_at' => $run->created_at->toIso8601String(),
            ];
        }

        $bag->reviewHistory = $reviewHistory;

        Log::info('ReviewHistoryCollector: Collected review history', [
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
            'previous_reviews' => count($reviewHistory),
        ]);
    }

    /**
     * Build a human-readable summary of findings.
     *
     * @param  array<string, int>  $severityCounts
     */
    private function buildSummary(array $severityCounts, int $totalFindings): string
    {
        if ($totalFindings === 0) {
            return 'No findings in previous review.';
        }

        $parts = [];

        // Order by severity
        $severityOrder = ['critical', 'high', 'medium', 'low', 'info'];

        foreach ($severityOrder as $severity) {
            if (isset($severityCounts[$severity]) && $severityCounts[$severity] > 0) {
                $count = $severityCounts[$severity];
                $label = $count === 1 ? $severity : $severity;
                $parts[] = sprintf('%d %s', $count, $label);
            }
        }

        if ($parts === []) {
            return sprintf('Previous review found %s finding(s).', $totalFindings);
        }

        return 'Previous review found: '.implode(', ', $parts).'.';
    }
}
