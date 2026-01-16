<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Enums\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Get code quality score trends over time.
 *
 * Quality score is calculated based on findings severity:
 * - critical: -10 points
 * - high: -5 points
 * - medium: -2 points
 * - low: -1 point
 * Starting from 100 and subtracting based on findings per run.
 */
final class QualityScoreTrendController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        $runs = $workspace->runs()
            ->with('findings')
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $trends = $runs
            ->groupBy(static fn (Run $run): string => $run->created_at->toDateString())
            ->map(static function (Collection $dayRuns, string $date): array {
                /** @var Collection<int, Run> $dayRuns */
                $scores = $dayRuns->map(static function (Run $run): int {
                    $deductions = $run->findings->sum(static function (Finding $finding): int {
                        $severity = $finding->severity;

                        return match (true) {
                            $severity === SentinelConfigSeverity::Critical => 10,
                            $severity === SentinelConfigSeverity::High => 5,
                            $severity === SentinelConfigSeverity::Medium => 2,
                            $severity === SentinelConfigSeverity::Low => 1,
                            default => 0,
                        };
                    });

                    return max(0, 100 - $deductions);
                });

                $avgScore = $scores->isNotEmpty() ? (float) $scores->average() : 100.0;

                return [
                    'date' => $date,
                    'quality_score' => max(0.0, round($avgScore, 2)),
                    'runs_count' => $dayRuns->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        return response()->json([
            'data' => $trends,
        ]);
    }
}
