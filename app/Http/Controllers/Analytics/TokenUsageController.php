<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Actions\Analytics\GetTokenUsage;
use App\Http\Requests\Analytics\AnalyticsQueryRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

/**
 * Get token usage over time from run metrics.
 */
final class TokenUsageController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AnalyticsQueryRequest $request, Workspace $workspace, GetTokenUsage $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle($workspace, $request->days()),
        ]);
    }
}
