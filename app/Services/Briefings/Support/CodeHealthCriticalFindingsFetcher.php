<?php

declare(strict_types=1);

namespace App\Services\Briefings\Support;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fetches top critical and high-severity findings.
 */
final readonly class CodeHealthCriticalFindingsFetcher
{
    private const int LIMIT = 10;

    /**
     * Fetch top critical findings from the given base query.
     *
     * @param  Builder<Finding>  $findingsQuery
     * @return array<int, array{id: int, title: string, severity: string|null, category: string|null, file_path: string|null, line_start: int|null}>
     */
    public function fetch(Builder $findingsQuery): array
    {
        return (clone $findingsQuery)
            ->whereIn('severity', [
                SentinelConfigSeverity::Critical->value,
                SentinelConfigSeverity::High->value,
            ])
            ->orderByRaw('CASE WHEN severity = ? THEN 0 ELSE 1 END', [SentinelConfigSeverity::Critical->value])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get(['id', 'title', 'severity', 'category', 'file_path', 'line_start'])
            ->map(fn (Finding $finding): array => [
                'id' => $finding->id,
                'title' => $finding->title,
                'severity' => $finding->severity?->value,
                'category' => $finding->category?->value,
                'file_path' => $finding->file_path,
                'line_start' => $finding->line_start,
            ])
            ->values()
            ->all();
    }
}
