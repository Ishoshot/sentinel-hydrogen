<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetReviewDurationTrends;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get review duration trends over time.
 */
final class ReviewDurationTrendsController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetReviewDurationTrends $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days()),
        ]);
    }
}
