<?php

declare(strict_types=1);

namespace App\Services\Reviews\Strategies;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Run;
use App\Services\Reviews\ValueObjects\AnnotationConfig;
use Illuminate\Support\Collection;

/**
 * Filters and orders findings eligible for annotation posting.
 */
final class EligibleFindingSelectionStrategy
{
    /**
     * @return Collection<int, Finding>
     */
    public function select(Run $run, AnnotationConfig $config): Collection
    {
        $policy = $run->policy_snapshot ?? [];
        $commentLimits = is_array($policy['comment_limits'] ?? null) ? $policy['comment_limits'] : [];

        $severityThreshold = $config->postThreshold;
        $maxComments = is_int($commentLimits['max_inline_comments'] ?? null) ? $commentLimits['max_inline_comments'] : 10;

        $minSeverityEnum = SentinelConfigSeverity::tryFrom($severityThreshold) ?? SentinelConfigSeverity::Medium;
        $minPriority = $minSeverityEnum->priority();

        return $run->findings
            ->filter(function (Finding $finding) use ($minPriority): bool {
                $findingPriority = $finding->severity?->priority() ?? 0;

                return $findingPriority >= $minPriority
                    && $finding->file_path !== null
                    && $finding->line_start !== null;
            })
            ->sortByDesc(fn (Finding $finding): int => $finding->severity?->priority() ?? 0)
            ->take($maxComments);
    }
}
