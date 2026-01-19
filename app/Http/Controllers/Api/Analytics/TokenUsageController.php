<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Get token usage over time from run metrics.
 */
final class TokenUsageController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        $runs = $workspace->runs()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('metrics')
            ->get();

        $usage = $runs->groupBy(static fn (Run $run): string => $run->created_at->toDateString())
            ->map(static function (Collection $dayRuns, string $date): array {
                /** @var Collection<int, Run> $dayRuns */
                $totalInput = $dayRuns->sum(static function (Run $run): int {
                    /** @var array<string, mixed>|null $metrics */
                    $metrics = $run->metrics;

                    if (! is_array($metrics)) {
                        return 0;
                    }

                    $inputTokens = $metrics['input_tokens'] ?? 0;

                    if (is_int($inputTokens)) {
                        return $inputTokens;
                    }

                    if (is_float($inputTokens) || is_string($inputTokens)) {
                        return (int) $inputTokens;
                    }

                    return 0;
                });

                $totalOutput = $dayRuns->sum(static function (Run $run): int {
                    /** @var array<string, mixed>|null $metrics */
                    $metrics = $run->metrics;

                    if (! is_array($metrics)) {
                        return 0;
                    }

                    $outputTokens = $metrics['output_tokens'] ?? 0;

                    if (is_int($outputTokens)) {
                        return $outputTokens;
                    }

                    if (is_float($outputTokens) || is_string($outputTokens)) {
                        return (int) $outputTokens;
                    }

                    return 0;
                });

                return [
                    'date' => $date,
                    'total_input_tokens' => $totalInput,
                    'total_output_tokens' => $totalOutput,
                    'total_tokens' => $totalInput + $totalOutput,
                ];
            })
            ->sortBy('date')
            ->values();

        return response()->json([
            'data' => $usage,
        ]);
    }
}
