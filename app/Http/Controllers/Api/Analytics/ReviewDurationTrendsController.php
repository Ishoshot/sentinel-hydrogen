<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Get review duration trends over time.
 */
final class ReviewDurationTrendsController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        $trends = $workspace->runs()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('AVG(duration_seconds) as avg_duration'),
                DB::raw('MIN(duration_seconds) as min_duration'),
                DB::raw('MAX(duration_seconds) as max_duration'),
            )
            ->whereNotNull('duration_seconds')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static function (Run $run): array {
                /** @var string $date */
                $date = (string) $run->getAttribute('date');
                /** @var float|int|string $avgDuration */
                $avgDuration = $run->getAttribute('avg_duration');
                /** @var float|int|string $minDuration */
                $minDuration = $run->getAttribute('min_duration');
                /** @var float|int|string $maxDuration */
                $maxDuration = $run->getAttribute('max_duration');

                return [
                    'date' => $date,
                    'avg_duration' => (int) $avgDuration,
                    'min_duration' => (int) $minDuration,
                    'max_duration' => (int) $maxDuration,
                ];
            });

        return response()->json([
            'data' => $trends,
        ]);
    }
}
