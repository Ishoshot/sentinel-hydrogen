<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetQualityScoreTrend;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get code quality score trends over time.
 */
final class QualityScoreTrendController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetQualityScoreTrend $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days()),
        ]);
    }
}
