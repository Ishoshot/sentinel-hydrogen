<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetReviewVelocity;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get review velocity showing reviews per time period.
 */
final class ReviewVelocityController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetReviewVelocity $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days(), $request->groupBy()),
        ]);
    }
}
