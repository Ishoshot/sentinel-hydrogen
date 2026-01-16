<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Get success vs failure rate for runs.
 */
final class SuccessRateController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        $runs = $workspace->runs()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $rates = $runs
            ->groupBy(static fn (Run $run): string => $run->created_at->toDateString())
            ->map(static function (Collection $dayRuns, string $date): array {
                /** @var Collection<int, Run> $dayRuns */
                $successful = $dayRuns->filter(static fn (Run $run): bool => $run->status === RunStatus::Completed)->count();
                $failed = $dayRuns->filter(static fn (Run $run): bool => $run->status === RunStatus::Failed)->count();
                $total = $dayRuns->count();
                $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;

                return [
                    'date' => $date,
                    'successful' => $successful,
                    'failed' => $failed,
                    'total' => $total,
                    'success_rate' => $successRate,
                ];
            })
            ->sortBy('date')
            ->values();

        return response()->json([
            'data' => $rates,
        ]);
    }
}
