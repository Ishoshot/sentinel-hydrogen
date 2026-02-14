<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Models\Finding;
use App\Models\Run;
use Illuminate\Support\Collection;

/**
 * Builds normalized review history entries from previous runs.
 */
final readonly class ReviewHistoryEntryBuilder
{
    /**
     * @return array{
     *   run_id: int,
     *   summary: string,
     *   findings_count: int,
     *   severity_breakdown: array<string, int>,
     *   key_findings: array<int, array{
     *     severity: string,
     *     category: string,
     *     title: string,
     *     file_path: string|null,
     *     line_start: int|null,
     *     fingerprint: string
     *   }>,
     *   created_at: string
     * }
     */
    public function build(Run $run, int $maxFindingsPerReview): array
    {
        $findings = $run->findings;
        $findingsCount = $findings->count();

        /** @var array<string, int> $severityCounts */
        $severityCounts = $findings->groupBy('severity')
            ->map(fn (Collection $group): int => $group->count())
            ->toArray();

        $summary = $this->buildSummary($severityCounts, $findingsCount);

        $keyFindings = $findings->take($maxFindingsPerReview)
            ->map(fn (Finding $finding): array => [
                'severity' => $finding->severity?->value ?? '',
                'category' => $finding->category?->value ?? '',
                'title' => (string) ($finding->title ?? ''),
                'file_path' => $finding->file_path,
                'line_start' => $finding->line_start,
                'fingerprint' => $this->generateFingerprint($finding),
            ])
            ->values()
            ->all();

        return [
            'run_id' => $run->id,
            'summary' => $summary,
            'findings_count' => $findingsCount,
            'severity_breakdown' => $severityCounts,
            'key_findings' => $keyFindings,
            'created_at' => $run->created_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int>  $severityCounts
     */
    private function buildSummary(array $severityCounts, int $totalFindings): string
    {
        if ($totalFindings === 0) {
            return 'No findings in previous review.';
        }

        $parts = [];
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

    /**
     * Generate a stable fingerprint for a finding snapshot.
     */
    private function generateFingerprint(Finding $finding): string
    {
        $components = [
            $finding->category?->value ?? '',
            $finding->file_path ?? '',
            $finding->title ?? '',
        ];

        return hash('xxh3', implode('|', $components));
    }
}
