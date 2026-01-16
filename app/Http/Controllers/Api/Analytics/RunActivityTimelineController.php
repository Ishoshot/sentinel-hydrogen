<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Get run activity timeline showing runs over time.
 */
final class RunActivityTimelineController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        $timeline = $workspace->runs()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $timeline,
        ]);
    }
}
