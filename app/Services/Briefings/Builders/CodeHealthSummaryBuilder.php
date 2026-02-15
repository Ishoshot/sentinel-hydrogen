<?php

declare(strict_types=1);

namespace App\Services\Briefings\Builders;

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates findings into severity and category breakdowns.
 */
final readonly class CodeHealthSummaryBuilder
{
    /**
     * Aggregate findings by severity and category.
     *
     * @param  Builder<Finding>  $findingsQuery
     * @return array{total_findings: int, severity_counts: array<string, int>, category_counts: array<string, int>}
     */
    public function aggregate(Builder $findingsQuery): array
    {
        $aggregateRow = (clone $findingsQuery)
            ->selectRaw('COUNT(*) as total_findings')
            ->selectRaw('severity')
            ->selectRaw('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('severity', 'category')
            ->get();

        $totalFindings = 0;
        $severityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        $categoryCounts = [];
        foreach (FindingCategory::cases() as $category) {
            $categoryCounts[$category->value] = 0;
        }

        foreach ($aggregateRow as $row) {
            $count = (int) $row->getAttribute('count');
            $totalFindings += $count;

            $severity = $row->severity instanceof SentinelConfigSeverity
                ? $row->severity->value
                : (string) $row->severity;
            if (array_key_exists($severity, $severityCounts)) {
                $severityCounts[$severity] += $count;
            }

            $category = $row->category instanceof FindingCategory
                ? $row->category->value
                : (string) $row->category;
            if ($category !== '' && isset($categoryCounts[$category])) {
                $categoryCounts[$category] += $count;
            }
        }

        return [
            'total_findings' => $totalFindings,
            'severity_counts' => $severityCounts,
            'category_counts' => $categoryCounts,
        ];
    }
}
