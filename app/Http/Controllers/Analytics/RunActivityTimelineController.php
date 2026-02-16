<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetRunActivityTimeline;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get run activity timeline showing runs over time.
 */
final class RunActivityTimelineController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetRunActivityTimeline $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days()),
        ]);
    }
}
