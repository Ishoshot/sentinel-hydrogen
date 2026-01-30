<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetOverviewMetrics;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get overview metrics for workspace analytics dashboard.
 */
final class OverviewMetricsController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace, GetOverviewMetrics $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace),
        ]);
    }
}
