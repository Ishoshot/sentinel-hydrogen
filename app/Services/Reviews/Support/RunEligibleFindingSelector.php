<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Run;
use Illuminate\Support\Collection;

/**
 * Filters and orders findings eligible for annotation posting.
 */
final class RunEligibleFindingSelector
{
    /**
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return Collection<int, Finding>
     */
    public function select(Run $run, array $config): Collection
    {
        $policy = $run->policy_snapshot ?? [];
        $commentLimits = is_array($policy['comment_limits'] ?? null) ? $policy['comment_limits'] : [];

        $severityThreshold = $config['post_threshold'];
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
